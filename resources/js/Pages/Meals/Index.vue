<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NutritionChart from '@/Components/Charts/NutritionChart.vue';
import CalendarWidget from '@/Components/CalendarWidget.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    meals: Object,
    chartData: Array,
    filters: Object,
    alerts: Array,
    stats: Object
});

const filters = ref({
    from: props.filters?.from ?? '',
    to: props.filters?.to ?? '',
    meal_type: props.filters?.meal_type ?? '',
});

const showAllData = ref(false);
const chartRange = ref(props.filters?.chart_range ?? '1week');

const updateChartFilters = () => {
    router.get(route('meals.index'), { ...filters.value, chart_range: chartRange.value }, { preserveState: true });
};

const applyFilters = () => {
    updateChartFilters();
    showAllData.value = true;
};

const clearFilters = () => {
    filters.value = { from: '', to: '', meal_type: '' };
    chartRange.value = '1week';
    router.get(route('meals.index'));
    showAllData.value = false;
};

const deleteMeal = (publicId) => {
    if (confirm('Are you sure you want to delete this meal record?')) {
        router.delete(route('meals.destroy', publicId));
    }
};

const timeRanges = [
    { value: 'today', label: 'D' },
    { value: '1week', label: 'W' },
    { value: '1month', label: 'M' },
    { value: '3month', label: '3M' },
    { value: '1year', label: 'Y' },
    { value: '5year', label: '5Y' }
];

const mealTypeOptions = [
    { value: '', label: 'All Meals' },
    { value: 'breakfast', label: 'Breakfast' },
    { value: 'lunch', label: 'Lunch' },
    { value: 'dinner', label: 'Dinner' },
    { value: 'snack', label: 'Snacks' },
    { value: 'other', label: 'Other' },
];

const changeChartRange = (range) => {
    chartRange.value = range;
    router.visit(route('meals.index'), {
        data: { chart_range: range, ...filters.value },
        only: ['chartData', 'filters'],
        preserveState: true,
        preserveScroll: true,
    });
};

const processedChart = computed(() => {
    if (!props.chartData || props.chartData.length === 0) {
        return { labels: [], calories: [], carbs: [] };
    }
    return {
        labels: props.chartData.map(d => d.date),
        calories: props.chartData.map(d => d.calories),
        carbs: props.chartData.map(d => d.carbs)
    };
});

const recentLogs = computed(() => props.meals.data.slice(0, 5));

const getMealColorInfo = (type) => {
    switch(type) {
        case 'breakfast': return { bg: 'bg-orange-100', text: 'text-orange-700', icon: '🍳' };
        case 'lunch': return { bg: 'bg-green-100', text: 'text-green-700', icon: '🥪' };
        case 'dinner': return { bg: 'bg-blue-100', text: 'text-blue-700', icon: '🍽️' };
        case 'snack': return { bg: 'bg-purple-100', text: 'text-purple-700', icon: '🍪' };
        default: return { bg: 'bg-gray-100', text: 'text-gray-700', icon: '🍲' };
    }
};

</script>

<template>
    <Head title="Meals & Nutrition" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Meals & Nutrition</h2>
                <div class="flex gap-2">
                    <Link :href="route('meals.create')"
                          class="px-4 py-2 bg-purple-600 text-white rounded-full hover:bg-purple-700 transition shadow text-sm font-medium">
                        + Log Meal
                    </Link>
                </div>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px] space-y-6">
                
                <!-- Dynamic Chart Layout (12/12 or 10/12 + 2/12 Alerts) -->
                <div class="flex flex-col lg:flex-row gap-6 mb-6">
                    
                    <!-- Chart Hero Section -->
                    <div :class="alerts && alerts.length > 0 ? 'lg:w-10/12' : 'w-full'" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300">
                        <!-- Top Header & Controls -->
                        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6">
                            
                            <!-- Left: Standard Daily Summary (e.g Today's Calorie + Carbs Tracked) -->
                            <div class="flex flex-wrap items-baseline gap-4">
                                <div>
                                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Today's Intake</h3>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-3xl font-extrabold tracking-tight text-orange-600">{{ stats?.today_calories || 0 }}</span>
                                        <span class="text-xs font-semibold text-gray-400">kcal</span>
                                    </div>
                                </div>
                                <div class="w-px h-8 bg-gray-200 mt-2"></div>
                                <div>
                                    <h3 class="text-xs font-bold text-transparent tracking-wider mb-1 select-none">.</h3>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-3xl font-extrabold tracking-tight text-purple-600">{{ stats?.today_carbs || 0 }}</span>
                                        <span class="text-xs font-semibold text-gray-400">g carbs</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: All Controls Inline -->
                            <div class="flex flex-wrap items-center gap-2">
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
                            </div>
                        </div>

                        <!-- Chart Container -->
                        <div class="h-80 w-full">
                            <NutritionChart v-if="processedChart.labels.length > 0"
                                :labels="processedChart.labels"
                                :caloriesData="processedChart.calories"
                                :carbsData="processedChart.carbs"
                            />
                            <div v-else class="h-full flex items-center justify-center text-gray-400 bg-gray-50 rounded-xl border border-gray-100 border-dashed">
                                No nutrition data for this period
                            </div>
                        </div>
                    </div>

                    <!-- Notification Area (2/12) -->
                    <div v-if="alerts && alerts.length > 0" class="lg:w-2/12 flex flex-col gap-4">
                        <div v-for="(alert, i) in alerts.slice(0, 3)" :key="i"
                             class="flex flex-col p-4 rounded-xl border relative overflow-hidden shadow-sm"
                             :class="alert.type === 'red' ? 'bg-red-50 border-red-200' : 'bg-amber-50 border-amber-200'">
                            
                            <div class="absolute left-0 top-0 bottom-0 w-1" :class="alert.type === 'red' ? 'bg-red-500' : 'bg-amber-500'"></div>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-lg" v-if="alert.type === 'red'">⏰</span>
                                <span class="text-lg" v-else>⏱️</span>
                                <h4 class="font-bold text-xs" :class="alert.type === 'red' ? 'text-red-900' : 'text-amber-900'">{{ alert.message.split(':')[0] }}</h4>
                            </div>
                            
                            <p class="text-xs mb-3 font-medium" :class="alert.type === 'red' ? 'text-red-700' : 'text-amber-700'">{{ alert.message.split(':')[1] }}</p>
                            
                            <div class="mt-auto">
                                <Link :href="route('meals.create')" class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-white shadow-sm border block text-center transition hover:shadow"
                                      :class="alert.type === 'red' ? 'text-red-700 border-red-200 hover:bg-red-50' : 'text-amber-700 border-amber-200 hover:bg-amber-50'">
                                    Log Meal
                                </Link>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Split Layout: Recent Logs & Calendar -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Logs List (1/3) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden lg:col-span-1 flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex-shrink-0 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-800">Recent Meals</h3>
                        </div>
                        <div v-if="recentLogs.length > 0" class="divide-y divide-gray-50 flex-1 overflow-y-auto max-h-[400px]">
                            <div v-for="meal in recentLogs" :key="meal.public_id" class="p-5 flex flex-col gap-2 hover:bg-gray-50 transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" 
                                             :class="getMealColorInfo(meal.meal_type).bg">
                                            <span class="text-lg">{{ getMealColorInfo(meal.meal_type).icon }}</span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900 capitalize">{{ meal.meal_type }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ new Date(meal.eaten_at_utc).toLocaleString([], { weekday: 'short', hour:'2-digit', minute:'2-digit'}) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-bold text-orange-600">{{ meal.total_calories_kcal || 0 }} <span class="font-normal text-xs text-orange-400">kcal</span></div>
                                        <div class="text-sm font-bold text-purple-600">{{ meal.total_carbs_g || 0 }} <span class="font-normal text-xs text-purple-400">g carbs</span></div>
                                    </div>
                                </div>
                                <div class="w-full mt-2 pl-12 text-xs text-gray-500 italic truncate">
                                    <span v-for="(item, idx) in meal.items" :key="idx">
                                        {{ idx > 0 ? ', ' : '' }}{{ item.food_name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-8 text-center text-gray-400 flex-1 flex items-center justify-center">No recent meals logged.</div>
                    </div>

                    <!-- Calendar Widget (2/3) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
                        <!-- We re-use CalendarWidget passing chartData. The widget handles generic 'readings' array with full_date. -->
                        <CalendarWidget :readings="chartData" />
                    </div>
                </div>

                <!-- Show All Data Accordion -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <button @click="showAllData = !showAllData" class="w-full px-6 py-4 flex justify-between items-center hover:bg-gray-50 transition focus:outline-none">
                        <h3 class="text-lg font-semibold text-gray-800">Show Detailed History</h3>
                        <span class="text-gray-400 transform transition-transform" :class="showAllData ? 'rotate-180' : ''">▼</span>
                    </button>
                    
                    <div v-if="showAllData" class="border-t border-gray-100 p-6">
                        <!-- Filters -->
                        <div class="bg-gray-50 rounded-xl p-4 mb-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 items-end">
                                <div>
                                    <label class="text-xs text-gray-500 mb-1 block">From</label>
                                    <input type="date" v-model="filters.from" class="w-full text-sm rounded-lg border-gray-300 bg-white" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 mb-1 block">To</label>
                                    <input type="date" v-model="filters.to" class="w-full text-sm rounded-lg border-gray-300 bg-white" />
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

                        <!-- Meals Table -->
                        <div class="overflow-x-auto rounded-xl border border-gray-100 mb-4">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-orange-600 uppercase tracking-wider">Calories</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-purple-600 uppercase tracking-wider">Carbs</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="meal in meals.data" :key="meal.public_id" class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 text-gray-900 whitespace-nowrap font-medium">
                                            {{ new Date(meal.eaten_at_utc).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider" :class="getMealColorInfo(meal.meal_type).bg + ' ' + getMealColorInfo(meal.meal_type).text">
                                                {{ meal.meal_type }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 min-w-[200px]">
                                            <div class="text-xs text-gray-600 line-clamp-2 leading-relaxed">
                                                <span v-for="(item, idx) in meal.items" :key="item.id">
                                                    {{ idx > 0 ? ', ' : '' }}<b>{{ item.food_name }}</b> ({{ item.calories_kcal }} kcal)
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-orange-600 whitespace-nowrap">{{ meal.total_calories_kcal || 0 }} kcal</td>
                                        <td class="px-4 py-3 font-semibold text-purple-600 whitespace-nowrap">{{ meal.total_carbs_g || 0 }} g</td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <Link :href="route('meals.edit', meal.public_id)" class="text-indigo-600 hover:underline text-xs mr-3 font-medium">Edit</Link>
                                            <button @click="deleteMeal(meal.public_id)" class="text-red-500 hover:underline text-xs font-medium">Delete</button>
                                        </td>
                                    </tr>
                                    <tr v-if="meals.data.length === 0">
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No records found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="meals.links && meals.links.length > 3" class="mt-4 flex justify-center gap-1 flex-wrap">
                            <Link v-for="link in meals.links" :key="link.label"
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
