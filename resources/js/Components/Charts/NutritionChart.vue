<template>
    <div class="w-full h-64 bg-white relative">
        <Chart type="bar" :data="chartData" :options="chartOptions" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Tooltip,
    Legend
} from 'chart.js';
import { Chart } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    BarElement,
    LineElement,
    PointElement,
    Tooltip,
    Legend
);

const props = defineProps({
    labels: { type: Array, required: true },
    caloriesData: { type: Array, required: true },
    carbsData: { type: Array, required: true }
});

const chartData = computed(() => {
    return {
        labels: props.labels,
        datasets: [
            {
                type: 'bar',
                label: 'Calories (kcal)',
                data: props.caloriesData,
                backgroundColor: 'rgba(249, 115, 22, 0.8)', // Orange-500
                borderColor: '#ea580c', // Orange-600
                borderWidth: 1,
                borderRadius: 4,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Carbs (g)',
                data: props.carbsData,
                borderColor: '#a855f7', // Purple-500
                backgroundColor: 'rgba(168, 85, 247, 0.2)',
                borderWidth: 2,
                pointBackgroundColor: '#a855f7',
                pointRadius: 3,
                tension: 0.3,
                yAxisID: 'y1'
            }
        ]
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
            align: 'end',
            labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } }
        },
        tooltip: {
            backgroundColor: '#1f2937',
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
            usePointStyle: true
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            ticks: { maxTicksLimit: 7, color: '#9ca3af', font: { size: 11 } }
        },
        y: {
            type: 'linear',
            display: true,
            position: 'left',
            grid: { color: '#f3f4f6', drawBorder: false, borderDash: [5, 5] },
            ticks: { maxTicksLimit: 5, color: '#9ca3af', font: { size: 11 } },
            title: { display: true, text: 'Calories', color: '#ea580c', font: { size: 10 } }
        },
        y1: {
            type: 'linear',
            display: true,
            position: 'right',
            grid: { drawOnChartArea: false }, 
            ticks: { maxTicksLimit: 5, color: '#9ca3af', font: { size: 11 } },
            title: { display: true, text: 'Carbs (g)', color: '#a855f7', font: { size: 10 } }
        }
    },
    layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } },
    animation: { duration: 400 }
}));
</script>
