<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    labels: { type: Array, required: true },
    dosesData: { type: Array, required: true },
});

const chartData = computed(() => {
    return {
        labels: props.labels,
        datasets: [
            {
                label: 'Doses Taken',
                data: props.dosesData,
                backgroundColor: 'rgba(147, 51, 234, 0.7)', // Purple-600 with opacity
                borderColor: 'rgb(147, 51, 234)',
                borderWidth: 1,
                borderRadius: 4,
            }
        ]
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: 'rgba(255, 255, 255, 0.95)',
            titleColor: '#1f2937',
            bodyColor: '#4b5563',
            borderColor: '#e5e7eb',
            borderWidth: 1,
            padding: 12,
            boxPadding: 4,
            usePointStyle: true,
            titleFont: { size: 13, weight: 'bold' },
            bodyFont: { size: 12 },
        }
    },
    scales: {
        x: {
            grid: { display: false, drawBorder: false },
            ticks: {
                font: { size: 11, weight: '500' },
                color: '#9ca3af',
                maxTicksLimit: 8,
                maxRotation: 0,
            }
        },
        y: {
            beginAtZero: true,
            grid: {
                color: '#f3f4f6',
                drawBorder: false,
            },
            ticks: {
                stepSize: 1,
                font: { size: 11, weight: '500' },
                color: '#9ca3af',
                callback: function(value) {
                    if (value % 1 === 0) return value;
                    return null;
                }
            },
            title: {
                display: true,
                text: 'Doses',
                color: '#9ca3af',
                font: { size: 10, weight: 'bold' }
            }
        }
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
    }
};
</script>

<template>
    <div class="h-full w-full">
        <Bar :options="chartOptions" :data="chartData" />
    </div>
</template>
