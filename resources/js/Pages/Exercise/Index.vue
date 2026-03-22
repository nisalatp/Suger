<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivityChart from '@/Components/Charts/ActivityChart.vue';

const props = defineProps({
    exercises: Object,
    filters: Object,
    chartData: { type: Array, default: () => [] },
    alerts: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const showTable = ref(false);

const deleteExercise = (publicId) => {
    if (confirm('Delete this exercise logic permanently?')) {
        router.delete(route('exercise.destroy', publicId));
    }
};

const filterForm = useForm({
    chart_range: props.filters?.chart_range || '1week',
});

const updateChartRange = (range) => {
    filterForm.chart_range = range;
    filterForm.get(route('exercise.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['chartData', 'filters', 'stats', 'alerts']
    });
};

const chartLabels = computed(() => props.chartData.map(d => d.date));
const chartDurations = computed(() => props.chartData.map(d => Number(d.duration).toFixed(0)));
const chartCalories = computed(() => props.chartData.map(d => Number(d.calories).toFixed(0)));
</script>

<template>
    <Head title="Exercise and Activity" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center w-full min-w-0">
                <h2 class="text-xl font-bold text-gray-900 tracking-tight shrink-0">Activity</h2>
                <div class="flex items-center gap-3 pl-4">
                    <Link :href="route('exercise.create')" class="px-4 py-2 bg-blue-500 text-white rounded-xl hover:bg-blue-600 transition shadow-sm text-sm font-bold tracking-wide flex items-center gap-2 whitespace-nowrap">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Log Activity
                    </Link>
                </div>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px]">
                
                <!-- TOP SECTION: CHART + ALERTS -->
                <div class="flex flex-col lg:flex-row gap-5 mb-5">
                    
                    <!-- 10/12 CHART -->
                    <div :class="['w-full transition-all duration-300', alerts.length > 0 ? 'lg:w-10/12' : 'lg:w-full']">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col h-full relative overflow-hidden">
                            <div class="absolute inset-0 bg-blue-50/20 pointer-events-none"></div>
                            
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 relative z-10 gap-3">
                                <div>
                                    <div class="flex items-end gap-3">
                                        <div>
                                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Duration</p>
                                            <div class="text-3xl font-extrabold text-gray-900 tracking-tight flex items-baseline gap-1">
                                                {{ stats.today_duration || 0 }} <span class="text-base text-gray-500 font-medium">min</span>
                                            </div>
                                        </div>
                                        <div class="pb-1">
                                            <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded text-xs font-bold leading-none">
                                                {{ stats.today_calories || 0 }} kcal
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex p-0.5 bg-gray-100/80 rounded-lg shrink-0 overflow-x-auto max-w-full">
                                    <button v-for="range in ['today', '1week', '1month', '3month', '1year', '5year']" :key="range"
                                            @click="updateChartRange(range)"
                                            :class="['px-3 py-1.5 text-xs font-bold rounded-md transition-all whitespace-nowrap', filterForm.chart_range === range ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700']">
                                        {{ range === 'today' ? 'D' : range === '1week' ? 'W' : range === '1month' ? 'M' : range === '3month' ? '3M' : range === '1year' ? 'Y' : '5Y' }}
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 relative z-10 min-h-[250px]">
                                <ActivityChart :labels="chartLabels" :durationData="chartDurations" :caloriesData="chartCalories" />
                            </div>
                        </div>
                    </div>

                    <!-- 2/12 ALERTS -->
                    <div v-if="alerts.length > 0" class="w-full lg:w-2/12 flex flex-col gap-3">
                        <div v-for="(alert, idx) in alerts" :key="idx"
                             :class="['rounded-2xl p-4 shadow-sm h-full flex flex-col justify-center border', 
                                alert.type === 'red' ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100']">
                            <div :class="['w-8 h-8 rounded-full flex items-center justify-center mb-3', 
                                  alert.type === 'red' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600']">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <h4 :class="['font-bold text-sm mb-1 leading-tight', alert.type === 'red' ? 'text-red-900' : 'text-amber-900']">{{ alert.message }}</h4>
                            <p :class="['text-xs font-medium', alert.type === 'red' ? 'text-red-700' : 'text-amber-700']">{{ alert.time }}</p>
                        </div>
                    </div>
                </div>

                <!-- SPLIT LAYOUT: RECENT ACTIVITY (1/3) & INTEGRATIONS (2/3) -->
                <div class="flex flex-col lg:flex-row gap-5 mb-5">
                    
                    <!-- RECENT ACTIVITY (1/3) -->
                    <div class="w-full lg:w-1/3">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 h-full overflow-hidden flex flex-col">
                            <div class="px-5 py-4 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center shrink-0">
                                <h3 class="font-bold text-gray-900">Recent Logs</h3>
                            </div>
                            <div class="flex-1 p-3 overflow-y-auto max-h-[400px]">
                                <div class="space-y-2">
                                    <div v-for="ex in exercises.data.slice(0, 5)" :key="ex.public_id" class="p-3 rounded-xl hover:bg-gray-50 transition border border-transparent hover:border-gray-100 group relative">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 border border-blue-200 shadow-sm">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex justify-between items-baseline mb-0.5">
                                                    <h4 class="font-bold text-gray-900 text-sm truncate capitalize">{{ ex.activity_type }}</h4>
                                                    <span class="text-orange-600 text-xs font-bold">{{ ex.calories_burned }} kcal</span>
                                                </div>
                                                <p class="text-xs text-gray-500 font-medium truncate">{{ ex.duration_minutes }} min · {{ new Date(ex.performed_at_utc).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="!exercises.data.length" class="py-8 text-center bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2 text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-500">No activity recent</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ACTIVITY INTEGRATIONS (2/3) -->
                    <div class="w-full lg:w-2/3">
                        <div class="bg-gradient-to-br from-indigo-900 to-blue-900 rounded-2xl shadow-sm border border-indigo-800 p-8 h-full flex flex-col justify-center items-center text-center relative overflow-hidden">
                            <!-- Background flair -->
                            <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full blur-3xl -mt-20 -mr-20"></div>
                            <div class="absolute bottom-0 left-0 w-48 h-48 bg-blue-400/10 rounded-full blur-2xl -mb-10 -ml-10"></div>
                            
                            <div class="relative z-10">
                                <div class="flex items-center justify-center gap-4 mb-6">
                                    <div class="w-16 h-16 bg-white rounded-2xl shadow-lg flex items-center justify-center p-3 animate-pulse">
                                        <!-- Apple Health Icon placeholder -->
                                        <svg class="h-8 w-8 text-red-500" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                                    </div>
                                    <div class="text-white text-2xl opacity-50">+</div>
                                    <div class="w-16 h-16 bg-white rounded-2xl shadow-lg flex items-center justify-center p-3">
                                        <!-- Google Fit Icon placeholder -->
                                        <svg class="h-8 w-8 text-blue-500" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                    </div>
                                </div>
                                <h3 class="text-2xl font-bold text-white mb-3">Sync Your Wearables</h3>
                                <p class="text-indigo-200 font-medium mb-6 max-w-md mx-auto">Future integration supporting Apple Health and Google Fit will automatically sync your steps, workouts, and calories burned.</p>
                                <button type="button" class="px-6 py-3 bg-white/10 hover:bg-white/20 text-white border border-white/20 rounded-xl font-bold transition backdrop-blur-sm cursor-not-allowed">
                                    Integration Coming Soon
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COLLAPSIBLE HISTORY TABLE -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <button @click="showTable = !showTable" class="w-full px-6 py-4 flex justify-between items-center bg-gray-50/50 hover:bg-gray-50 transition">
                        <span class="font-bold text-gray-900">All Logged Activity</span>
                        <div class="flex items-center gap-3 text-sm text-gray-500 font-medium">
                            <span v-if="!showTable">{{ exercises.total }} records hidden</span>
                            <span v-else>Hide Data</span>
                            <svg :class="['w-5 h-5 transition-transform', showTable ? 'rotate-180' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </button>
                    
                    <div v-show="showTable" class="border-t border-gray-100 p-5">
                        <div class="overflow-x-auto rounded-xl border border-gray-100">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 font-bold uppercase text-[11px] tracking-wider">
                                        <th class="px-4 py-3 border-b">Time</th>
                                        <th class="px-4 py-3 border-b">Activity</th>
                                        <th class="px-4 py-3 border-b">Intensity</th>
                                        <th class="px-4 py-3 border-b text-right">Duration</th>
                                        <th class="px-4 py-3 border-b text-right">Calories</th>
                                        <th class="px-4 py-3 border-b w-16 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="ex in exercises.data" :key="ex.public_id" class="hover:bg-gray-50/50 transition">
                                        <td class="px-4 py-3 text-gray-500 font-medium">{{ new Date(ex.performed_at_utc).toLocaleString() }}</td>
                                        <td class="px-4 py-3 font-bold text-gray-900 capitalize">{{ ex.activity_type }}</td>
                                        <td class="px-4 py-3">
                                            <span :class="['px-2 py-1 rounded text-xs font-bold capitalize', 
                                                ex.intensity === 'high' ? 'bg-red-100 text-red-700' : 
                                                ex.intensity === 'moderate' ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700']">
                                                {{ ex.intensity }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900">{{ ex.duration_minutes }} min</td>
                                        <td class="px-4 py-3 text-right font-bold text-orange-600">{{ ex.calories_burned }} kcal</td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <button @click="deleteExercise(ex.public_id)" class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition" title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!exercises.data.length">
                                        <td colspan="6" class="px-4 py-12 text-center text-gray-400 border-b-0">No exercise logs found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
