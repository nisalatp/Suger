<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import { Line, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, LineElement, BarElement,
    PointElement, Title, Tooltip, Legend, Filler
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, LineElement, BarElement, PointElement, Title, Tooltip, Legend, Filler);

const props = defineProps({
    patient:    Object,
    access:     Boolean,
    permission: Object,
    data:       Object,
});

// ── Timeline filter ───────────────────────────────────────────────────────────
const PRESETS = [
    { key: '1d',  label: '1D' },
    { key: '1w',  label: '1W' },
    { key: '1m',  label: '1M' },
    { key: '3m',  label: '3M' },
    { key: '1y',  label: '1Y' },
    { key: '5y',  label: '5Y' },
    { key: 'all', label: 'All' },
];

const activePreset = ref('all');
const customFrom   = ref('');
const customTo     = ref('');

/** Returns the cutoff Date for a preset, or null for 'all'. */
const presetFrom = (key) => {
    const now = new Date();
    if (key === '1d')  return new Date(now - 864e5);
    if (key === '1w')  return new Date(now - 7 * 864e5);
    if (key === '1m')  { const d = new Date(now); d.setMonth(d.getMonth() - 1); return d; }
    if (key === '3m')  { const d = new Date(now); d.setMonth(d.getMonth() - 3); return d; }
    if (key === '1y')  { const d = new Date(now); d.setFullYear(d.getFullYear() - 1); return d; }
    if (key === '5y')  { const d = new Date(now); d.setFullYear(d.getFullYear() - 5); return d; }
    return null; // 'all'
};

/** Earliest date across all data modules (to hide useless range presets). */
const earliestDataDate = computed(() => {
    const allDates = [
        ...(props.data?.glucose     ?? []),
        ...(props.data?.meals       ?? []),
        ...(props.data?.medications ?? []),
        ...(props.data?.exercise    ?? []),
    ].map(r => new Date(r.date)).filter(d => !isNaN(d));
    return allDates.length ? new Date(Math.min(...allDates)) : null;
});

/** Only show presets that would actually filter data (cutoff is after earliest record). */
const visiblePresets = computed(() =>
    PRESETS.filter(p => {
        if (p.key === 'all') return true;   // always show All
        const cutoff = presetFrom(p.key);
        if (!cutoff) return true;
        // Hide if the cutoff is on or before the earliest record — preset adds no filtering value
        return !earliestDataDate.value || cutoff > earliestDataDate.value;
    })
);


const filterRow = (row) => {
    const dateStr = row.date ?? '';
    const d = new Date(dateStr);

    if (activePreset.value === 'custom') {
        if (customFrom.value && d < new Date(customFrom.value)) return false;
        if (customTo.value   && d > new Date(customTo.value + 'T23:59:59')) return false;
        return true;
    }

    const cutoff = presetFrom(activePreset.value);
    if (cutoff && d < cutoff) return false;
    return true;
};

const selectPreset = (key) => {
    activePreset.value = key;
    if (key !== 'custom') { customFrom.value = ''; customTo.value = ''; }
};

// ── Filtered data slices ──────────────────────────────────────────────────────
const fGlucose     = computed(() => (props.data?.glucose     ?? []).filter(filterRow));
const fMeals       = computed(() => (props.data?.meals       ?? []).filter(filterRow));
const fMedications = computed(() => (props.data?.medications ?? []).filter(filterRow));
const fExercise    = computed(() => (props.data?.exercise    ?? []).filter(filterRow));

// ── Tabs ─────────────────────────────────────────────────────────────────────
const activeTab = ref(
    props.data?.glucose      ? 'glucose' :
    props.data?.meals        ? 'meals' :
    props.data?.medications  ? 'medications' : 'exercise'
);

const tabs = computed(() =>
    ['glucose', 'meals', 'medications', 'exercise']
        .filter(m => props.data?.[m])
        .map(m => ({
            key: m,
            label: { glucose: '🩸 Glucose', meals: '🍽️ Meals', medications: '💊 Medications', exercise: '🏃 Exercise' }[m]
        }))
);

// ── Chart configs ─────────────────────────────────────────────────────────────
const parseD = (str) => {
    if (!str) return null;
    const d = new Date(typeof str === 'string' ? str.replace(' ', 'T') : str);
    return isNaN(d) ? null : d;
};

const buildXAxis = (preset) => {
    const hairline = { color: 'rgba(0,0,0,0.04)', display: true };
    const noGrid   = { display: false };

    const base = (gridCfg, cb, limitOpts = {}) => ({
        grid: gridCfg,
        ticks: { maxRotation: 0, ...limitOpts, callback: cb },
    });

    if (preset === '1d') {
        return base(noGrid, function (value) {
            const d = parseD(this.getLabelForValue(value));
            if (!d) return '';
            const h = d.getHours(), m = d.getMinutes();
            // Only show on the hour or every 2 hours
            if (m !== 0) return '';
            if (h % 2 !== 0 && this.chart.data.labels.length > 48) return '';
            return `${String(h).padStart(2,'0')}:00`;
        }, { maxTicksLimit: 13 });
    }

    if (preset === '1w') {
        return base(hairline, function (value) {
            const labels = this.chart.data.labels;
            const d    = parseD(labels[value]);
            if (!d) return '';
            const prev = value > 0 ? parseD(labels[value - 1]) : null;
            const newDay = !prev || d.toDateString() !== prev.toDateString();
            if (!newDay) return '';
            return d.toLocaleDateString('en', { weekday: 'short', day: 'numeric' });
        });
    }

    if (preset === '1m') {
        return base(hairline, function (value) {
            const labels = this.chart.data.labels;
            const d    = parseD(labels[value]);
            if (!d) return '';
            const prev = value > 0 ? parseD(labels[value - 1]) : null;
            const newDay = !prev || d.toDateString() !== prev.toDateString();
            if (!newDay) return '';
            // Only label Mondays + the 1st of the month
            const isWeekStart = d.getDay() === 1;
            const isMonthStart = d.getDate() === 1;
            if (!isWeekStart && !isMonthStart && prev) return '';
            return d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
        });
    }

    if (preset === '3m' || preset === '1y') {
        return base(hairline, function (value) {
            const labels = this.chart.data.labels;
            const d    = parseD(labels[value]);
            if (!d) return '';
            const prev = value > 0 ? parseD(labels[value - 1]) : null;
            const newMonth = !prev
                || d.getMonth() !== prev.getMonth()
                || d.getFullYear() !== prev.getFullYear();
            if (!newMonth) return '';
            return d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
        });
    }

    if (preset === '5y' || preset === 'all') {
        return base(hairline, function (value) {
            const labels = this.chart.data.labels;
            const d    = parseD(labels[value]);
            if (!d) return '';
            const prev = value > 0 ? parseD(labels[value - 1]) : null;
            const newYear  = !prev || d.getFullYear() !== prev.getFullYear();
            const newMonth = !prev || d.getMonth() !== prev.getMonth() || newYear;
            if (newYear)  return `${d.toLocaleDateString('en', { month: 'short' })} '${String(d.getFullYear()).slice(2)}`;
            if (newMonth) return d.toLocaleDateString('en', { month: 'short' });
            return '';
        });
    }

    // custom / fallback — auto-detect range
    return base(hairline, function (value) {
        const labels = this.chart.data.labels;
        const d    = parseD(labels[value]);
        if (!d) return '';
        const prev = value > 0 ? parseD(labels[value - 1]) : null;
        const newDay = !prev || d.toDateString() !== prev.toDateString();
        if (!newDay) return '';
        return d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
    }, { maxTicksLimit: 10 });
};

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: { mode: 'index', intersect: false },
    },
    scales: {
        x: buildXAxis(activePreset.value),
        y: { beginAtZero: false },
    },
}));

const isSummary = computed(() => props.permission?.format === 'summary');

const glucoseChart = computed(() => {
    const d = fGlucose.value;
    if (!d.length) return null;
    return {
        labels: d.map(r => r.date),
        datasets: [{
            label: isSummary.value ? 'Avg Glucose (mg/dL)' : 'Glucose (mg/dL)',
            data: d.map(r => isSummary.value ? r.avg : r.value),
            borderColor: '#7c3aed',
            backgroundColor: '#7c3aed22',
            tension: 0.35, fill: true, pointRadius: d.length > 60 ? 0 : 3,
        }],
    };
});

const mealsChart = computed(() => {
    const d = fMeals.value;
    if (!d.length) return null;
    return {
        labels: d.map(r => r.date),
        datasets: [{
            label: 'Calories (kcal)',
            data: d.map(r => r.calories),
            backgroundColor: '#f9731699',
            borderColor: '#f97316',
            borderWidth: 1, borderRadius: 4,
        }],
    };
});

const exerciseChart = computed(() => {
    const d = fExercise.value;
    if (!d.length) return null;
    return {
        labels: d.map(r => r.date),
        datasets: [{
            label: isSummary.value ? 'Duration (min)' : 'Activity',
            data: d.map(r => isSummary.value ? r.duration : r.minutes),
            borderColor: '#16a34a',
            backgroundColor: '#16a34a22',
            tension: 0.35, fill: true, pointRadius: d.length > 60 ? 0 : 3,
        }],
    };
});

const periodLabel = (t) => ({
    all: 'All Time', last_30: 'Last 30 Days', last_90: 'Last 90 Days',
    last_year: 'Last Year', custom: 'Custom Range',
}[t] ?? t);
</script>

<template>
    <Head :title="`${patient?.name ?? 'Patient'} — Data View`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('patients.index')" class="text-gray-400 hover:text-purple-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </Link>
                <div class="flex items-center gap-3">
                    <img v-if="patient?.avatar_url" :src="patient.avatar_url" class="w-9 h-9 rounded-xl object-cover" />
                    <div v-else class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center text-purple-700 font-bold">
                        {{ patient?.name?.charAt(0) }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ patient?.name }}</h2>
                        <p class="text-xs text-gray-400">
                            {{ access ? `${permission.format === 'summary' ? 'Daily summaries' : 'Raw records'} · ${periodLabel(permission.period_type)}` : 'Access restricted' }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px] space-y-4">

                <!-- No Access -->
                <div v-if="!access" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">Data Sharing Restricted</h3>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto">
                        This patient has not enabled data sharing with you, or has turned it off. Ask them to configure sharing in their Doctors section.
                    </p>
                </div>

                <template v-else>
                    <!-- Permission summary pills -->
                    <div class="flex flex-wrap gap-2">
                        <span v-for="[mod, on] in Object.entries(permission.modules).filter(([,v]) => v)" :key="mod"
                              class="flex items-center gap-1 px-3 py-1.5 bg-purple-50 text-purple-700 rounded-full text-xs font-semibold border border-purple-100">
                            <span>{{ { glucose:'🩸', meals:'🍽️', medications:'💊', exercise:'🏃' }[mod] }}</span>
                            <span class="capitalize">{{ mod }}</span>
                        </span>
                        <span class="px-3 py-1.5 bg-gray-50 text-gray-500 rounded-full text-xs border border-gray-100">
                            {{ permission.format === 'summary' ? 'Daily Summaries' : 'Raw Records' }}
                        </span>
                    </div>

                    <!-- ── Timeline filter bar ──────────────────────────────── -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-4 py-3 flex flex-wrap items-center gap-3">
                        <!-- Preset chips -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button v-for="p in visiblePresets" :key="p.key"
                                @click="selectPreset(p.key)"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition border',
                                    activePreset === p.key
                                        ? 'bg-purple-600 text-white border-purple-600 shadow-sm'
                                        : 'text-gray-600 border-gray-200 hover:border-purple-300 hover:text-purple-700']">
                                {{ p.label }}
                            </button>
                            <button @click="selectPreset('custom')"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition border',
                                    activePreset === 'custom'
                                        ? 'bg-purple-600 text-white border-purple-600 shadow-sm'
                                        : 'text-gray-600 border-gray-200 hover:border-purple-300 hover:text-purple-700']">
                                Custom
                            </button>
                        </div>

                        <!-- Custom date pickers (shown when 'Custom' active) -->
                        <Transition
                            enter-active-class="transition duration-150"
                            enter-from-class="opacity-0 translate-x-2"
                            leave-active-class="transition duration-100"
                            leave-to-class="opacity-0 translate-x-2">
                            <div v-if="activePreset === 'custom'" class="flex items-center gap-2 flex-wrap">
                                <div class="w-px h-5 bg-gray-200 hidden sm:block" />
                                <label class="text-xs text-gray-400 font-medium">From</label>
                                <input v-model="customFrom" type="date"
                                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-purple-400 text-gray-700" />
                                <label class="text-xs text-gray-400 font-medium">To</label>
                                <input v-model="customTo" type="date"
                                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-purple-400 text-gray-700" />
                            </div>
                        </Transition>

                        <!-- Record count badge -->
                        <div class="ml-auto text-xs text-gray-400 font-medium tabular-nums">
                            <span class="font-bold text-purple-700">{{
                                activeTab === 'glucose'     ? fGlucose.length :
                                activeTab === 'meals'       ? fMeals.length :
                                activeTab === 'medications' ? fMedications.length :
                                fExercise.length
                            }}</span> records
                        </div>
                    </div>

                    <!-- Tab navigation + data panel -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="flex border-b border-gray-100">
                            <button v-for="tab in tabs" :key="tab.key"
                                    @click="activeTab = tab.key"
                                    class="px-6 py-4 text-sm font-semibold transition border-b-2 -mb-px"
                                    :class="activeTab === tab.key ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:text-gray-700'">
                                {{ tab.label }}
                            </button>
                        </div>

                        <!-- ── Glucose ── -->
                        <div v-if="activeTab === 'glucose' && data.glucose" class="p-5">
                            <div v-if="fGlucose.length" class="h-64 mb-6">
                                <Line :data="glucoseChart" :options="chartOptions" />
                            </div>
                            <p v-else class="text-center text-sm text-gray-400 py-8">No readings in this period.</p>
                            <div v-if="fGlucose.length" class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                                            <th class="pb-2 pr-4">Date</th>
                                            <th class="pb-2 pr-4">{{ permission.format === 'summary' ? 'Avg (mg/dL)' : 'Value (mg/dL)' }}</th>
                                            <th v-if="permission.format === 'summary'" class="pb-2 pr-4">Min</th>
                                            <th v-if="permission.format === 'summary'" class="pb-2 pr-4">Max</th>
                                            <th v-if="permission.format === 'summary'" class="pb-2">Readings</th>
                                            <th v-else class="pb-2">Context</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="r in fGlucose" :key="r.date" class="hover:bg-gray-50">
                                            <td class="py-2 pr-4 text-gray-600">{{ r.date }}</td>
                                            <td class="py-2 pr-4 font-semibold"
                                                :class="(permission.format === 'summary' ? r.avg : r.value) > 180 ? 'text-red-600' : (permission.format === 'summary' ? r.avg : r.value) < 70 ? 'text-amber-600' : 'text-green-600'">
                                                {{ permission.format === 'summary' ? r.avg : r.value }}
                                            </td>
                                            <td v-if="permission.format === 'summary'" class="py-2 pr-4 text-gray-500">{{ r.min }}</td>
                                            <td v-if="permission.format === 'summary'" class="py-2 pr-4 text-gray-500">{{ r.max }}</td>
                                            <td v-if="permission.format === 'summary'" class="py-2 text-gray-400">{{ r.count }}</td>
                                            <td v-else class="py-2 text-gray-400 capitalize">{{ r.context }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── Meals ── -->
                        <div v-if="activeTab === 'meals' && data.meals" class="p-5">
                            <div v-if="fMeals.length" class="h-64 mb-6">
                                <Bar :data="mealsChart" :options="chartOptions" />
                            </div>
                            <p v-else class="text-center text-sm text-gray-400 py-8">No meal records in this period.</p>
                            <div v-if="fMeals.length" class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                                            <th class="pb-2 pr-4">Date</th>
                                            <th class="pb-2 pr-4">Calories (kcal)</th>
                                            <th class="pb-2 pr-4">Carbs (g)</th>
                                            <th v-if="permission.format === 'summary'" class="pb-2">Meals</th>
                                            <th v-else class="pb-2">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="r in fMeals" :key="r.date" class="hover:bg-gray-50">
                                            <td class="py-2 pr-4 text-gray-600">{{ r.date }}</td>
                                            <td class="py-2 pr-4 font-semibold text-orange-600">{{ r.calories }}</td>
                                            <td class="py-2 pr-4 text-purple-600">{{ r.carbs }}</td>
                                            <td v-if="permission.format === 'summary'" class="py-2 text-gray-400">{{ r.count }}</td>
                                            <td v-else class="py-2 text-gray-400 capitalize">{{ r.type }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── Medications ── -->
                        <div v-if="activeTab === 'medications' && data.medications" class="p-5">
                            <p v-if="!fMedications.length" class="text-center text-sm text-gray-400 py-8">No medication records in this period.</p>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                                            <th class="pb-2 pr-4">Date</th>
                                            <th v-if="permission.format !== 'summary'" class="pb-2 pr-4">Medication</th>
                                            <th v-if="permission.format !== 'summary'" class="pb-2">Dose</th>
                                            <th v-else class="pb-2">Doses Taken</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="r in fMedications" :key="r.date" class="hover:bg-gray-50">
                                            <td class="py-2 pr-4 text-gray-600">{{ r.date }}</td>
                                            <td v-if="permission.format !== 'summary'" class="py-2 pr-4 text-blue-700 font-medium">{{ r.medication }}</td>
                                            <td v-if="permission.format !== 'summary'" class="py-2 text-gray-500">{{ r.dose }}</td>
                                            <td v-else class="py-2 font-semibold text-blue-700">{{ r.doses }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- ── Exercise ── -->
                        <div v-if="activeTab === 'exercise' && data.exercise" class="p-5">
                            <div v-if="fExercise.length" class="h-64 mb-6">
                                <Line :data="exerciseChart" :options="chartOptions" />
                            </div>
                            <p v-else class="text-center text-sm text-gray-400 py-8">No exercise records in this period.</p>
                            <div v-if="fExercise.length" class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-left text-xs text-gray-400 uppercase tracking-wide">
                                            <th class="pb-2 pr-4">Date</th>
                                            <th v-if="permission.format === 'summary'" class="pb-2 pr-4">Sessions</th>
                                            <th class="pb-2 pr-4">Duration (min)</th>
                                            <th class="pb-2">Cal Burned</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="r in fExercise" :key="r.date" class="hover:bg-gray-50">
                                            <td class="py-2 pr-4 text-gray-600">{{ r.date }}</td>
                                            <td v-if="permission.format === 'summary'" class="py-2 pr-4 text-gray-400">{{ r.sessions }}</td>
                                            <td class="py-2 pr-4 font-semibold text-green-700">{{ r.duration ?? r.minutes }}</td>
                                            <td class="py-2 text-orange-600">{{ r.calories ?? '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
