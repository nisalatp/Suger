<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Sparkline from '@/Components/Sparkline.vue';
import MetabolicChart from '@/Components/Charts/MetabolicChart.vue';

const props = defineProps({
    lastReading: Object,
    sevenDayAvg: Number,
    fastingAvg: Number,
    postMealAvg: Number,
    todayCount: Number,
    alerts: Array,
    recentChartData: Array,
    profile: Object,
    chartRange: { type: String, default: '1week' },
    metabolicChart: Object
});

const getStatusColor = (value) => {
    if (!value) return 'text-gray-400';
    if (value < 70) return 'text-red-500';
    if (value > 180) return 'text-red-500';
    if (value > 130) return 'text-yellow-500';
    return 'text-green-500';
};

const getAlertIcon = (type) => {
    if (type === 'high') return '⬆️';
    if (type === 'low') return '⬇️';
    return '🔔';
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
    router.visit(route('dashboard'), {
        data: { chart_range: range },
        only: ['metabolicChart', 'chartRange'],
        preserveState: true,
        preserveScroll: true,
    });
};

const allReadings = computed(() => props.recentChartData?.map(r => Number(r.value_mgdl)) || []);
const fastingReadings = computed(() => props.recentChartData?.filter(r => r.is_fasting).map(r => Number(r.value_mgdl)) || []);
const postMealReadings = computed(() => props.recentChartData?.filter(r => !r.is_fasting).map(r => Number(r.value_mgdl)) || []);
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px]">
                <!-- Summary Tiles -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                    <!-- Last Reading -->
                    <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-500 mb-1">Last Reading</div>
                            <div v-if="lastReading" :class="['text-3xl font-bold', getStatusColor(lastReading.value_mgdl)]">
                                {{ lastReading.value_mgdl }} <span class="text-sm font-normal text-gray-400">mg/dL</span>
                            </div>
                            <div v-else class="text-3xl font-bold text-gray-300">—</div>
                            <div v-if="lastReading" class="text-xs text-gray-400 mt-1">
                                {{ lastReading.time_of_day?.replace(/_/g, ' ') }}
                            </div>
                        </div>
                        <Sparkline v-if="allReadings.length > 1" :data="allReadings.slice(-10)" color="#3b82f6" bgColor="rgba(59, 130, 246, 0.1)" />
                    </div>

                    <!-- 7-Day Average -->
                    <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-500 mb-1">7-Day Average</div>
                            <div :class="['text-3xl font-bold', getStatusColor(sevenDayAvg)]">
                                {{ sevenDayAvg ?? '—' }} <span v-if="sevenDayAvg" class="text-sm font-normal text-gray-400">mg/dL</span>
                            </div>
                        </div>
                        <Sparkline v-if="allReadings.length > 1" :data="allReadings" color="#eab308" bgColor="rgba(234, 179, 8, 0.1)" />
                    </div>

                    <!-- Fasting Avg -->
                    <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-500 mb-1">Fasting Avg (7d)</div>
                            <div :class="['text-3xl font-bold', getStatusColor(fastingAvg)]">
                                {{ fastingAvg ?? '—' }} <span v-if="fastingAvg" class="text-sm font-normal text-gray-400">mg/dL</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">Target: 80–130 mg/dL</div>
                        </div>
                        <Sparkline v-if="fastingReadings.length > 1" :data="fastingReadings" color="#22c55e" bgColor="rgba(34, 197, 94, 0.1)" />
                    </div>

                    <!-- Post-meal Avg -->
                    <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-500 mb-1">Post-meal Avg (7d)</div>
                            <div :class="['text-3xl font-bold', getStatusColor(postMealAvg)]">
                                {{ postMealAvg ?? '—' }} <span v-if="postMealAvg" class="text-sm font-normal text-gray-400">mg/dL</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-1">Target: &lt;180 mg/dL</div>
                        </div>
                        <Sparkline v-if="postMealReadings.length > 1" :data="postMealReadings" color="#ef4444" bgColor="rgba(239, 68, 68, 0.1)" />
                    </div>
                </div>

                <!-- Metabolic Chart -->
                <div class="bg-white rounded-xl shadow p-6 mb-8 flex flex-col">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                        <h3 class="text-lg font-semibold text-gray-800">Metabolic Overview</h3>
                        <!-- Time Ranges -->
                        <div class="flex bg-gray-100 p-0.5 rounded shadow-inner">
                            <button v-for="tr in timeRanges" :key="tr.value" 
                                    @click="changeChartRange(tr.value)"
                                    :class="['px-3 py-1 text-[11px] uppercase tracking-wider font-bold rounded transition', chartRange === tr.value ? 'bg-white text-gray-900 shadow border border-gray-100' : 'text-gray-500 hover:text-gray-700']">
                                {{ tr.label }}
                            </button>
                        </div>
                    </div>
                    <div v-if="metabolicChart && metabolicChart.labels.length > 0">
                        <MetabolicChart 
                            :labels="metabolicChart.labels"
                            :intakeData="metabolicChart.intakeData"
                            :burnData="metabolicChart.burnData"
                            :netData="metabolicChart.netData"
                            :glucoseData="metabolicChart.glucoseData"
                        />
                    </div>
                    <div v-else class="h-80 flex items-center justify-center text-gray-400 bg-gray-50 rounded-xl border border-gray-100 border-dashed">
                        No metabolic data available for this range
                    </div>
                </div>

                <!-- Alerts & Quick Actions -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Alerts -->
                    <div class="bg-white rounded-xl shadow p-6 lg:col-span-2 flex flex-col">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Alerts & Reminders</h3>
                            <span class="text-sm text-gray-400">{{ todayCount }} readings today</span>
                        </div>
                        <div v-if="alerts && alerts.length > 0" class="space-y-3 flex-1">
                            <div v-for="(alert, i) in alerts" :key="i"
                                 :class="[
                                     'flex items-center gap-3 p-3 rounded-lg border', 
                                     alert.type === 'high' ? 'bg-red-50 border-red-100 text-red-900' : 
                                     (alert.type === 'low' ? 'bg-orange-50 border-orange-100 text-orange-900' : 
                                     'bg-blue-50 border-blue-100 text-blue-900')
                                 ]">
                                <span class="text-xl shrink-0">{{ getAlertIcon(alert.type) }}</span>
                                <div>
                                    <div class="text-sm font-medium">{{ alert.message }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-gray-400 text-sm py-8 text-center flex-1 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-xl">
                            <span class="text-3xl mb-2">✨</span>
                            All caught up! No alerts right now.
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <Link :href="route('glucose.create')"
                                  class="block w-full text-center py-3 px-4 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                                + Add Reading
                            </Link>
                            <Link :href="route('meals.create')"
                                  class="block w-full text-center py-3 px-4 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                                + Log Meal
                            </Link>
                            <Link :href="route('exercise.create')"
                                  class="block w-full text-center py-3 px-4 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                                + Log Exercise
                            </Link>
                            <Link :href="route('reports.index')"
                                  class="block w-full text-center py-3 px-4 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition font-medium">
                                📊 Reports
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Profile Summary -->
                <div v-if="profile" class="bg-white rounded-xl shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Your Profile</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Diabetes Type</span>
                            <div class="font-medium text-gray-800 capitalize">{{ profile.diabetes_type?.replace(/_/g, ' ') ?? 'Not set' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Blood Group</span>
                            <div class="font-medium text-gray-800">{{ profile.blood_group ?? 'Not set' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">BMI</span>
                            <div class="font-medium text-gray-800">{{ profile.bmi ?? 'Not set' }}</div>
                        </div>
                        <div>
                            <Link :href="route('profile.edit')" class="text-purple-600 hover:underline text-sm">
                                Edit Profile →
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
