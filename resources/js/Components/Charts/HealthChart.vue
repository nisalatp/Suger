<template>
    <div class="w-full h-64 bg-white relative">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
    Filler
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Tooltip,
    Legend,
    Filler
);

const props = defineProps({
    labels: { type: Array, required: true },
    datasets: { type: Array, required: true },
    yMin: { type: Number, default: 40 },
    yMax: { type: Number, default: 300 },
    showLegend: { type: Boolean, default: false }
});

const chartData = computed(() => {
    return {
        labels: props.labels,
        datasets: props.datasets
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { 
            display: props.showLegend, 
            position: 'top', 
            align: 'end',
            labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } 
        },
        tooltip: {
            backgroundColor: '#1f2937',
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
            usePointStyle: true,
            callbacks: {
                label: (context) => `${context.dataset.label ? context.dataset.label + ': ' : ''}${context.parsed.y} mg/dL`
            }
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            ticks: { maxTicksLimit: 7, color: '#9ca3af', font: { size: 11 } },
        },
        y: {
            min: props.yMin,
            max: props.yMax,
            grid: { color: '#f3f4f6', drawBorder: false, borderDash: [5, 5] },
            ticks: { maxTicksLimit: 5, color: '#9ca3af', font: { size: 11 } }
        }
    },
    layout: { padding: { top: 10, right: 10, bottom: 0, left: 0 } },
    animation: { duration: 400 }
}));
</script>
