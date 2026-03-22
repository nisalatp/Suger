<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend, Filler } from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, Title, Tooltip, Legend, Filler);

const props = defineProps({ patients: Array });

const search = ref('');
const selectedIds = ref([]);
const compareModule = ref('glucose');
const compareData = ref(null);
const comparing = ref(false);

const filtered = computed(() =>
    props.patients.filter(p =>
        p.name?.toLowerCase().includes(search.value.toLowerCase()) ||
        p.email?.toLowerCase().includes(search.value.toLowerCase())
    )
);

const toggleSelect = (id) => {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(x => x !== id);
    } else if (selectedIds.value.length < 6) {
        selectedIds.value.push(id);
    }
};

const loadComparison = async () => {
    if (!selectedIds.value.length) return;
    comparing.value = true;
    try {
        const res = await fetch(route('patients.compare'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ patient_ids: selectedIds.value, module: compareModule.value }),
        });
        const json = await res.json();
        compareData.value = json.series;
    } finally {
        comparing.value = false;
    }
};

const chartColors = ['#7c3aed', '#2563eb', '#16a34a', '#d97706', '#dc2626', '#0891b2'];

const comparisonChart = computed(() => {
    if (!compareData.value?.length) return null;
    const allDates = [...new Set(compareData.value.flatMap(s => s.points.map(p => p.x)))].sort();
    return {
        labels: allDates,
        datasets: compareData.value.map((series, i) => ({
            label: series.name,
            data: allDates.map(d => {
                const pt = series.points.find(p => p.x === d);
                return pt ? pt.y : null;
            }),
            borderColor: chartColors[i % chartColors.length],
            backgroundColor: chartColors[i % chartColors.length] + '22',
            tension: 0.35,
            fill: false,
            spanGaps: true,
            pointRadius: 3,
        }))
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: true, position: 'top' },
        tooltip: { mode: 'index', intersect: false }
    },
    scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: false }
    }
};

const moduleLabel = { glucose: 'Blood Glucose (mg/dL)', meals: 'Calories (kcal)', exercise: 'Calories Burned', medications: 'Doses' };
</script>

<template>
    <Head title="My Patients — Doctor Portal" />
    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-bold text-gray-900">My Patients</h2>
                <p class="text-sm text-gray-500 mt-0.5">View and compare health data for your linked patients</p>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px] space-y-5">

                <!-- Comparison Panel -->
                <div v-if="selectedIds.length > 0" class="bg-white rounded-2xl shadow-sm border border-purple-200 overflow-hidden">
                    <div class="p-5 border-b border-gray-100 flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-gray-700">Compare:</span>
                            <div class="flex gap-2">
                                <button v-for="m in [{k:'glucose',l:'Glucose'},{k:'meals',l:'Meals'},{k:'exercise',l:'Exercise'},{k:'medications',l:'Meds'}]" :key="m.k"
                                        @click="compareModule = m.k"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                                        :class="compareModule === m.k ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    {{ m.l }}
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span class="font-medium text-gray-700">{{ selectedIds.length }}</span> patient{{ selectedIds.length > 1 ? 's' : '' }} selected
                            <button @click="selectedIds = []" class="text-red-400 hover:underline">Clear</button>
                        </div>
                        <button @click="loadComparison" :disabled="comparing"
                                class="ml-auto px-5 py-2 bg-purple-600 text-white rounded-xl text-sm font-semibold hover:bg-purple-700 transition disabled:opacity-50">
                            {{ comparing ? 'Loading…' : 'Load Comparison' }}
                        </button>
                    </div>

                    <div v-if="comparisonChart" class="p-5 h-72">
                        <Line :data="comparisonChart" :options="chartOptions" />
                    </div>
                    <div v-else class="p-8 text-center text-gray-400 text-sm">
                        Select metric above and click "Load Comparison" to overlay patient data.
                    </div>
                </div>

                <!-- Search + Tip -->
                <div class="flex items-center gap-4">
                    <div class="relative flex-1 max-w-sm">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" v-model="search" placeholder="Search patients…"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:ring-purple-500 focus:border-purple-500" />
                    </div>
                    <p class="text-xs text-gray-400">Click patient cards to select for comparison (up to 6)</p>
                </div>

                <!-- Empty -->
                <div v-if="!filtered.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-800 mb-2">No Patients Yet</h3>
                    <p class="text-sm text-gray-500 max-w-sm mx-auto">
                        Patients who add your email to their Doctors list will automatically appear here once you have doctor mode enabled.
                    </p>
                </div>

                <!-- Patient Cards Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div v-for="p in filtered" :key="p.id"
                         @click="toggleSelect(p.id)"
                         class="bg-white rounded-2xl shadow-sm border-2 transition-all cursor-pointer hover:shadow-md"
                         :class="selectedIds.includes(p.id) ? 'border-purple-500 shadow-purple-100' : 'border-gray-100 hover:border-purple-200'">
                        <div class="p-5">
                            <!-- Patient info -->
                            <div class="flex items-start gap-4">
                                <div class="relative shrink-0">
                                    <img v-if="p.avatar_url" :src="p.avatar_url" :alt="p.name"
                                         class="w-12 h-12 rounded-xl object-cover" />
                                    <div v-else class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center text-purple-700 font-bold text-lg">
                                        {{ p.name?.charAt(0) }}
                                    </div>
                                    <!-- Selection indicator -->
                                    <div v-if="selectedIds.includes(p.id)"
                                         class="absolute -top-1 -right-1 w-5 h-5 bg-purple-600 rounded-full flex items-center justify-center">
                                        <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold text-gray-900 truncate">{{ p.name }}</h3>
                                    <p class="text-xs text-gray-400 truncate">{{ p.email }}</p>
                                    <!-- Permission badges -->
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        <span v-if="!p.permission" class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Not configured</span>
                                        <span v-else-if="!p.permission.active" class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Sharing off</span>
                                        <template v-else>
                                            <span v-for="[mod, allowed] in Object.entries(p.permission.modules).filter(([,v]) => v)" :key="mod"
                                                  class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full capitalize">
                                                {{ mod }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick stat -->
                            <div v-if="p.last_glucose" class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                                <span class="text-xs text-gray-400">Last Glucose</span>
                                <span class="text-sm font-bold text-purple-700">{{ p.last_glucose }} <span class="text-xs font-normal text-gray-400">mg/dL</span></span>
                            </div>
                        </div>

                        <!-- View full data button -->
                        <div class="px-5 pb-4">
                            <Link :href="route('patients.show', p.public_id)"
                                  @click.stop
                                  class="block w-full text-center py-2 text-sm font-semibold text-purple-700 border border-purple-200 rounded-xl hover:bg-purple-50 transition">
                                View Patient Data →
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
