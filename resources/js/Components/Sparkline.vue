<template>
  <div class="h-12 w-full mt-auto pt-2">
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
  Filler,
  Tooltip
} from 'chart.js';
import { Line } from 'vue-chartjs';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip
);

const props = defineProps({
  data: {
    type: Array,
    required: true
  },
  color: {
    type: String,
    default: '#8b5cf6' // purple-500
  },
  bgColor: {
    type: String,
    default: 'rgba(139, 92, 246, 0.1)'
  }
});

const chartData = computed(() => {
  return {
    labels: props.data.map((_, index) => index),
    datasets: [
      {
        data: props.data,
        borderColor: props.color,
        backgroundColor: props.bgColor,
        borderWidth: 2,
        pointRadius: 0,
        pointHoverRadius: 0,
        fill: true,
        tension: 0.4 // Smooth curve
      }
    ]
  };
});

const chartOptions = computed(() => {
  const minVal = Math.min(...props.data);
  const maxVal = Math.max(...props.data);
  const padding = (maxVal - minVal) * 0.1;

  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { enabled: false }
    },
    scales: {
      x: { display: false },
      y: {
        display: false,
        min: minVal - padding,
        max: maxVal + padding
      }
    },
    layout: {
      padding: 0
    },
    animation: {
      duration: 0
    }
  };
});
</script>
