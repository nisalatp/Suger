<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HealthChart from '@/Components/Charts/HealthChart.vue';
import CalendarWidget from '@/Components/CalendarWidget.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    readings: Object,
    chartData: Array,
    chartRange: { type: String, default: 'month' },
    alerts: Array,
    filters: Object,
});

const filters = ref({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    meal_type: props.filters?.meal_type ?? '',
    meal_timing: props.filters?.meal_timing ?? '',
    fasting: props.filters?.fasting ?? '',
});

const showAllData = ref(false);
const chartMode = ref('scatter'); // 'scatter' or 'average'

const updateChartFilters = () => {
    router.get(route('glucose.index'), { ...filters.value, chart_range: props.chartRange }, { preserveState: true });
};

const applyFilters = () => {
    updateChartFilters();
    showAllData.value = true;
};

const clearFilters = () => {
    filters.value = { from: '', to: '', meal_type: '', meal_timing: '', fasting: '' };
    router.get(route('glucose.index'), { chart_range: props.chartRange });
    showAllData.value = false;
};

const deleteReading = (publicId) => {
    if (confirm('Are you sure you want to delete this reading?')) {
        router.delete(route('glucose.destroy', publicId));
    }
};

const getStatusColor = (value) => {
    if (value < 70) return '#ef4444'; // red
    if (value > 180) return '#ef4444'; // red
    if (value > 130) return '#eab308'; // yellow
    return '#22c55e'; // green
};

const timeRanges = [
    { value: 'today', label: 'D' },
    { value: '1week', label: 'W' },
    { value: '1month', label: 'M' },
    { value: '3month', label: '3M' },
    { value: '1year', label: 'Y' },
    { value: '5year', label: '5Y' }
];

const changeChartRange = (range) => {
    router.visit(route('glucose.index'), {
        data: { chart_range: range, ...filters.value },
        only: ['chartData', 'chartRange'],
        preserveState: true,
        preserveScroll: true,
    });
};

const mealTypeOptions = [
    { value: '', label: 'All Meals' },
    { value: 'breakfast', label: 'Breakfast' },
    { value: 'lunch', label: 'Lunch' },
    { value: 'dinner', label: 'Dinner' },
    { value: 'bedtime', label: 'Bedtime' },
    { value: 'overnight', label: 'Overnight' },
];

const mealTimingOptions = [
    { value: '', label: 'All Timing' },
    { value: 'pre', label: 'Pre-Meal' },
    { value: 'post', label: 'After Meal' },
];

const processedChart = computed(() => {
    if (!props.chartData || props.chartData.length === 0) {
        return { labels: [], datasets: [], showLegend: false };
    }

    if (chartMode.value === 'scatter') {
        const labels = props.chartData.map(r => {
            const d = new Date(r.measured_at_utc);
            return `${d.getDate()} ${d.toLocaleString('default', { month: 'short' })}`;
        });
        const datasets = [{
            label: 'Glucose',
            data: props.chartData.map(r => Number(r.value_mgdl)),
            borderColor: 'transparent',
            pointBackgroundColor: props.chartData.map(r => r.is_fasting ? '#3b82f6' : getStatusColor(Number(r.value_mgdl))),
            pointBorderColor: '#ffffff',
            pointBorderWidth: 1,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: false,
            tension: 0
        }];
        return { labels, datasets, showLegend: false };
    } else {
        // Averages Mode (Daily grouping)
        const grouped = props.chartData.reduce((acc, obj) => {
            const d = new Date(obj.measured_at_utc);
            // Grouping by yyyy-mm-dd
            const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            if (!acc[key]) acc[key] = [];
            acc[key].push(obj);
            return acc;
        }, {});

        const sortedKeys = Object.keys(grouped).sort();
        const labels = sortedKeys.map(k => {
            const d = new Date(k);
            return `${d.getDate()} ${d.toLocaleString('default', { month: 'short' })}`;
        });

        const fastingAvgData = [];
        const nonFastingAvgData = [];

        sortedKeys.forEach(k => {
            const dayReadings = grouped[k];
            
            const fasting = dayReadings.filter(r => r.is_fasting);
            if (fasting.length > 0) {
                const avg = fasting.reduce((sum, r) => sum + Number(r.value_mgdl), 0) / fasting.length;
                fastingAvgData.push(Math.round(avg));
            } else {
                fastingAvgData.push(null);
            }
            
            const nonFasting = dayReadings.filter(r => !r.is_fasting);
            if (nonFasting.length > 0) {
                const avg = nonFasting.reduce((sum, r) => sum + Number(r.value_mgdl), 0) / nonFasting.length;
                nonFastingAvgData.push(Math.round(avg));
            } else {
                nonFastingAvgData.push(null);
            }
        });

        const datasets = [
            {
                label: 'Fasting Avg',
                data: fastingAvgData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                pointRadius: 2,
                pointBackgroundColor: '#3b82f6',
                spanGaps: true,
                fill: true,
                tension: 0.4
            },
            {
                label: 'Non-Fasting Avg',
                data: nonFastingAvgData,
                borderColor: '#eab308',
                backgroundColor: 'rgba(234, 179, 8, 0.1)',
                borderWidth: 2,
                pointRadius: 2,
                pointBackgroundColor: '#eab308',
                spanGaps: true,
                fill: true,
                tension: 0.4
            }
        ];
        return { labels, datasets, showLegend: true };
    }
});

const latestReading = computed(() => props.readings.data[0] || null);
const recentLogs = computed(() => props.readings.data.slice(0, 5));

</script>

<template>
    <Head title="Blood Glucose" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Blood Glucose</h2>
                <Link :href="route('glucose.create')"
                      class="px-4 py-2 bg-purple-600 text-white rounded-full hover:bg-purple-700 transition shadow text-sm font-medium">
                    + Add Data
                </Link>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px] space-y-6">
                
                <!-- Dynamic Chart Layout (12/12 or 10/12 + 2/12 Alerts) -->
                <div class="flex flex-col lg:flex-row gap-6 mb-6">
                    
                    <!-- Chart Hero Section -->
                    <div :class="alerts && alerts.length > 0 ? 'lg:w-10/12' : 'w-full'" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                        <!-- Top Header & Controls -->
                        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-4">
                            
                            <!-- Left: Latest Reading -->
                            <div class="flex flex-wrap items-baseline gap-3">
                                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Latest</h3>
                                <template v-if="latestReading">
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-3xl font-extrabold tracking-tight text-gray-900" :style="{ color: getStatusColor(latestReading.value_mgdl) }">
                                            {{ latestReading.value_mgdl }}
                                        </span>
                                        <span class="text-xs font-semibold text-gray-400">mg/dL</span>
                                    </div>
                                    <div class="flex items-center text-xs font-medium text-gray-500 sm:border-l border-gray-200 sm:pl-3 ml-1">
                                        {{ new Date(latestReading.measured_at_utc).toLocaleString([], { weekday: 'short', hour: '2-digit', minute:'2-digit' }) }}
                                        <span class="mx-1.5 text-gray-300">•</span> 
                                        <span class="capitalize">{{ latestReading.time_of_day?.replace(/_/g, ' ') }}</span>
                                        <span v-if="latestReading.is_fasting" class="text-blue-500 ml-1.5 bg-blue-50 px-1.5 py-0.5 rounded-sm">Fasting</span>
                                    </div>
                                </template>
                                <span v-else class="text-xl font-bold text-gray-300">No Data</span>
                            </div>

                            <!-- Right: All Controls Inline -->
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Meal Filters -->
                                <select v-model="filters.meal_timing" @change="updateChartFilters" class="text-xs rounded border-gray-200 bg-gray-50 focus:bg-white py-1 pl-2 pr-6 text-gray-700 font-medium cursor-pointer h-7">
                                    <option v-for="opt in mealTimingOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                                <select v-model="filters.meal_type" @change="updateChartFilters" class="text-xs rounded border-gray-200 bg-gray-50 focus:bg-white py-1 pl-2 pr-6 text-gray-700 font-medium cursor-pointer h-7">
                                    <option v-for="opt in mealTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                                
                                <div class="w-px h-5 bg-gray-200 mx-1 hidden sm:block"></div>

                                <!-- Time Ranges -->
                                <div class="flex bg-gray-100 p-0.5 rounded">
                                    <button v-for="tr in timeRanges" :key="tr.value" 
                                            @click="changeChartRange(tr.value)"
                                            :class="['px-2.5 py-1 text-[11px] uppercase tracking-wider font-bold rounded transition', chartRange === tr.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                                        {{ tr.label }}
                                    </button>
                                </div>
                                
                                <!-- View Mode -->
                                <div class="flex bg-gray-100 p-0.5 rounded">
                                    <button @click="chartMode = 'scatter'" 
                                            :class="['px-3 py-1 text-xs font-semibold rounded transition', chartMode === 'scatter' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                                        Points
                                    </button>
                                    <button @click="chartMode = 'average'" 
                                            :class="['px-3 py-1 text-xs font-semibold rounded transition', chartMode === 'average' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                                        Curves
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Container -->
                        <div class="h-80 w-full mb-3">
                            <HealthChart v-if="processedChart.datasets.length > 0"
                                :labels="processedChart.labels"
                                :datasets="processedChart.datasets"
                                :showLegend="processedChart.showLegend"
                            />
                            <div v-else class="h-full flex items-center justify-center text-gray-400 bg-gray-50 rounded-xl border border-gray-100 border-dashed">
                                No chart data for this period
                            </div>
                        </div>

                        <!-- Minified Bottom Legend -->
                        <div v-if="chartMode === 'scatter'" class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[11px] font-medium text-gray-600 border-t border-gray-100 pt-3">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                <span>Fasting</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                <span>Normal <span class="text-gray-400 ml-0.5 font-normal">(70-130)</span></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                                <span>Elevated <span class="text-gray-400 ml-0.5 font-normal">(131-180)</span></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                <span>High/Low <span class="text-gray-400 ml-0.5 font-normal">(&gt;180, &lt;70)</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Notification Area (2/12) -->
                    <div v-if="alerts && alerts.length > 0" class="lg:w-2/12 flex flex-col gap-4">
                        <div v-for="(alert, i) in alerts.slice(0, 3)" :key="i"
                             class="flex flex-col p-4 rounded-xl border relative overflow-hidden shadow-sm"
                             :class="alert.type === 'red' ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'">
                            <!-- Decorative accent line -->
                            <div class="absolute left-0 top-0 bottom-0 w-1" :class="alert.type === 'red' ? 'bg-red-500' : 'bg-amber-500'"></div>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-lg" v-if="alert.type === 'red'">⏰</span>
                                <span class="text-lg" v-else>⏱️</span>
                                <h4 class="font-bold text-xs" :class="alert.type === 'red' ? 'text-red-900' : 'text-amber-900'">{{ alert.title }}</h4>
                            </div>
                            
                            <p class="text-xs mb-3" :class="alert.type === 'red' ? 'text-red-700' : 'text-amber-700'">{{ alert.message }}</p>
                            
                            <div class="mt-auto">
                                <Link :href="route('glucose.create')" class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-white shadow-sm border block text-center transition hover:shadow"
                                      :class="alert.type === 'red' ? 'text-red-700 border-red-200 hover:bg-red-50' : 'text-amber-700 border-amber-200 hover:bg-amber-50'">
                                    Log Now
                                </Link>
                            </div>
                        </div>
                        <div v-if="alerts.length > 3" class="text-xs text-gray-400 text-center font-medium">
                            + {{ alerts.length - 3 }} more reminders
                        </div>
                    </div>

                </div>

                <!-- Split Layout: Recent Logs & Calendar -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Logs List (1/3) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden lg:col-span-1 flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex-shrink-0">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Logs</h3>
                        </div>
                        <div v-if="recentLogs.length > 0" class="divide-y divide-gray-50 flex-1 overflow-y-auto max-h-[400px]">
                            <div v-for="reading in recentLogs" :key="reading.public_id" class="p-6 flex items-center justify-between hover:bg-gray-50 transition">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gray-100 shrink-0" 
                                         :style="{ backgroundColor: getStatusColor(reading.value_mgdl) + '20', color: getStatusColor(reading.value_mgdl) }">
                                        <span class="font-bold border rounded-full w-8 h-8 flex items-center justify-center bg-white shadow-sm pt-0.5">
                                            🩸
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 truncate">{{ reading.value_mgdl }} <span class="text-sm font-normal text-gray-500">mg/dL</span></div>
                                        <div class="text-xs sm:text-sm text-gray-500 mt-0.5 truncate">
                                            {{ new Date(reading.measured_at_utc).toLocaleDateString() }} 
                                            <span class="capitalize px-1 sm:px-2 inline">• {{ reading.time_of_day?.replace(/_/g, ' ') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <Link :href="route('glucose.edit', reading.public_id)" class="text-purple-600 hover:bg-purple-50 px-2 sm:px-3 py-1.5 rounded-lg text-xs sm:text-sm font-medium transition shrink-0">
                                    Edit
                                </Link>
                            </div>
                        </div>
                        <div v-else class="p-8 text-center text-gray-400 flex-1 flex items-center justify-center">No recent logs.</div>
                    </div>

                    <!-- Calendar Widget (2/3) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
                        <CalendarWidget :readings="chartData" />
                    </div>
                </div>

                <!-- Show All Data Accordion -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <button @click="showAllData = !showAllData" class="w-full px-6 py-4 flex justify-between items-center hover:bg-gray-50 transition">
                        <h3 class="text-lg font-semibold text-gray-800">Show All Data</h3>
                        <span class="text-gray-400 transform transition-transform" :class="showAllData ? 'rotate-180' : ''">▼</span>
                    </button>
                    
                    <div v-if="showAllData" class="border-t border-gray-100 p-6">
                        <!-- Filters -->
                        <div class="bg-gray-50 rounded-xl p-4 mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3 items-end">
                                <div>
                                    <label class="text-xs text-gray-500 mb-1 block">From</label>
                                    <input type="date" v-model="filters.from" class="w-full text-sm rounded-lg border-gray-300 bg-white" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 mb-1 block">To</label>
                                    <input type="date" v-model="filters.to" class="w-full text-sm rounded-lg border-gray-300 bg-white" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 mb-1 block">Timing</label>
                                    <select v-model="filters.meal_timing" class="w-full text-sm rounded-lg border-gray-300 bg-white">
                                        <option v-for="opt in mealTimingOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 mb-1 block">Meal Type</label>
                                    <select v-model="filters.meal_type" class="w-full text-sm rounded-lg border-gray-300 bg-white">
                                        <option v-for="opt in mealTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                    </select>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="applyFilters" class="flex-1 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900 transition font-medium">Filter</button>
                                    <button @click="clearFilters" class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition bg-white text-gray-600">Clear</button>
                                </div>
                            </div>
                        </div>

                        <!-- Readings Table -->
                        <div class="overflow-x-auto rounded-xl border border-gray-100">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date/Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Context</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="reading in readings.data" :key="reading.public_id" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ new Date(reading.measured_at_utc).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="font-bold" :style="{ color: getStatusColor(reading.value_mgdl) }">{{ reading.value_mgdl }}</span>
                                            <span class="text-gray-400 text-xs ml-1">mg/dL</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs capitalize">{{ reading.time_of_day?.replace(/_/g, ' ') }}</span>
                                                <span v-if="reading.is_fasting" class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-xs font-medium">Fasting</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <Link :href="route('glucose.edit', reading.public_id)" class="text-purple-600 hover:underline text-xs mr-3 font-medium">Edit</Link>
                                            <button @click="deleteReading(reading.public_id)" class="text-red-500 hover:underline text-xs font-medium">Delete</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="readings.links && readings.links.length > 3" class="mt-4 flex justify-center gap-1 flex-wrap">
                            <Link v-for="link in readings.links" :key="link.label"
                                  :href="link.url || '#'"
                                  v-html="link.label"
                                  :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition', link.active ? 'bg-purple-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : '']" />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
