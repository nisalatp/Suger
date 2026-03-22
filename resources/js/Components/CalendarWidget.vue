<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    readings: {
        type: Array,
        default: () => []
    }
});

const currentDate = ref(new Date());

const currentMonth = computed(() => currentDate.value.getMonth());
const currentYear = computed(() => currentDate.value.getFullYear());

const monthName = computed(() => {
    return currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' });
});

const daysInMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
});

const firstDayOfMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value, 1).getDay();
});

const prevMonth = () => {
    currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1);
};

const isToday = (day) => {
    const today = new Date();
    return day === today.getDate() && currentMonth.value === today.getMonth() && currentYear.value === today.getFullYear();
};

const getDayReadings = (day) => {
    const targetDate = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return props.readings.filter(r => {
        const rDate = new Date(r.measured_at_utc);
        return `${rDate.getFullYear()}-${String(rDate.getMonth() + 1).padStart(2, '0')}-${String(rDate.getDate()).padStart(2, '0')}` === targetDate;
    });
};

const getStatusColor = (value) => {
    if (value < 70) return '#ef4444'; // red
    if (value > 180) return '#ef4444'; // red
    if (value > 130) return '#eab308'; // yellow
    return '#22c55e'; // green
};

const getDayTrend = (readings) => {
    if (!readings || readings.length === 0) return null;
    
    // Check for any alerts (red)
    if (readings.some(r => Number(r.value_mgdl) < 70 || Number(r.value_mgdl) > 180)) return 'bg-red-500';
    // Check for elevated (yellow)
    if (readings.some(r => Number(r.value_mgdl) > 130)) return 'bg-yellow-500';
    // Otherwise all normal
    return 'bg-green-500';
};
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800">{{ monthName }}</h3>
            <div class="flex gap-2">
                <button @click="prevMonth" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <button @click="nextMonth" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <!-- Days of Week -->
        <div class="grid grid-cols-7 gap-1 mb-2">
            <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="text-center text-xs font-semibold text-gray-400 uppercase tracking-wider py-1">
                {{ day }}
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2 flex-1 outline-none">
            <!-- Empty slots for alignment -->
            <div v-for="empty in firstDayOfMonth" :key="'empty-'+empty" class="aspect-square"></div>
            
            <!-- Dates -->
            <div v-for="day in daysInMonth" :key="day" 
                 class="aspect-square border rounded-xl flex flex-col relative transition group hover:shadow-md z-1"
                 :class="[
                    isToday(day) ? 'border-purple-300 bg-purple-50 flex-1' : 'border-gray-100 bg-white hover:border-purple-200'
                 ]">
                
                <span class="absolute top-1.5 left-2 text-xs font-bold" :class="isToday(day) ? 'text-purple-700' : 'text-gray-600'">
                    {{ day }}
                </span>

                <!-- Readings content container -->
                <div class="mt-auto px-1 sm:px-2 pb-1.5 sm:pb-2 pt-6 w-full flex flex-col gap-0.5">
                    <div v-if="getDayReadings(day).length > 0" class="flex flex-wrap gap-1 items-center">
                        <span class="w-1.5 h-1.5 rounded-full" :class="getDayTrend(getDayReadings(day))"></span>
                        <span class="text-[9px] sm:text-[10px] font-semibold text-gray-600 leading-none">
                            {{ getDayReadings(day).length }} logs
                        </span>
                    </div>

                    <!-- Show latest reading for the day on larger screens -->
                    <div v-if="getDayReadings(day).length > 0" class="hidden sm:block mt-0.5">
                        <span class="text-[10px] sm:text-xs font-bold" :style="{ color: getStatusColor(getDayReadings(day)[0].value_mgdl) }">
                            {{ getDayReadings(day)[0].value_mgdl }} <span class="font-normal text-gray-400 text-[9px]">mg/dL</span>
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>
