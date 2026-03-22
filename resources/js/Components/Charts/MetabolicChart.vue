<template>
    <div class="w-full h-80 bg-white relative">
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
    intakeData: { type: Array, required: true },
    burnData: { type: Array, required: true },
    netData: { type: Array, required: true },
    glucoseData: { type: Array, required: true }
});

const chartData = computed(() => {
    return {
        labels: props.labels,
        datasets: [
            {
                type: 'bar',
                label: 'Intake (kcal)',
                data: props.intakeData,
                backgroundColor: 'rgba(34, 197, 94, 0.7)', // Green
                borderRadius: 4,
                yAxisID: 'y'
            },
            {
                type: 'bar',
                label: 'Burn (kcal)',
                data: props.burnData,
                backgroundColor: 'rgba(234, 88, 12, 0.7)', // Orange
                borderRadius: 4,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Net Deficit/Excess',
                data: props.netData,
                borderColor: '#8b5cf6', // Violet
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderWidth: 2,
                pointBackgroundColor: '#8b5cf6',
                pointRadius: 3,
                tension: 0.3,
                yAxisID: 'y'
            },
            {
                type: 'line',
                label: 'Avg Glucose (mg/dL)',
                data: props.glucoseData,
                borderColor: '#3b82f6', // Blue
                backgroundColor: 'transparent',
                borderWidth: 3,
                borderDash: [5, 5],
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4,
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: {
            position: 'top',
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
            ticks: { maxTicksLimit: 10, color: '#9ca3af', font: { size: 11 } }
        },
        y: {
            type: 'linear',
            display: true,
            position: 'left',
            grid: { color: '#f3f4f6', drawBorder: false },
            ticks: { color: '#9ca3af', font: { size: 11 } },
            title: { display: true, text: 'Calories (kcal)', color: '#6b7280', font: { size: 10 } }
        },
        y1: {
            type: 'linear',
            display: true,
            position: 'right',
            grid: { drawOnChartArea: false }, 
            ticks: { color: '#3b82f6', font: { size: 11 } },
            title: { display: true, text: 'Glucose (mg/dL)', color: '#3b82f6', font: { size: 10 } }
        }
    },
    layout: { padding: { top: 10, right: 0, bottom: 0, left: 0 } },
    animation: { duration: 400 }
}));
</script>
