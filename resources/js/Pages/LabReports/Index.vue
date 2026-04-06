<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import HealthChart from '@/Components/Charts/HealthChart.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    reports:       Object,
    trendData:     Array,
    trendKey:      String,
    availableKeys: Array,
});

// Expanded rows set
const expandedRows = ref(new Set());
const toggle = (id) => {
    if (expandedRows.value.has(id)) expandedRows.value.delete(id);
    else expandedRows.value.add(id);
};

const statusBadge = (status) => ({
    low:     { cls: 'bg-yellow-100 text-yellow-800 border border-yellow-200', label: '⬇ Low' },
    normal:  { cls: 'bg-green-100 text-green-800 border border-green-200',   label: '✓ Normal' },
    high:    { cls: 'bg-red-100 text-red-800 border border-red-200',         label: '⬆ High' },
    unknown: { cls: 'bg-gray-100 text-gray-500 border border-gray-200',      label: '— Unknown' },
}[status] ?? { cls: 'bg-gray-100 text-gray-500', label: status });

// Markers summary per report
const abnormalCount = (report) => report.items?.filter(i => i.status === 'low' || i.status === 'high').length ?? 0;
const totalCount    = (report) => report.items?.length ?? 0;

// Trend chart
const selectedKey = ref(props.trendKey ?? '');

const changeTrend = (key) => {
    selectedKey.value = key;
    router.get(route('lab-reports.index'), { trend_key: key }, { preserveScroll: true, preserveState: true, only: ['trendData', 'trendKey'] });
};

const trendChartData = computed(() => {
    if (!props.trendData || props.trendData.length === 0) return null;
    const labels = props.trendData.map(r => {
        const d = new Date(r.reported_on);
        return `${d.getDate()} ${d.toLocaleString('default', { month: 'short' })} ${d.getFullYear()}`;
    });
    const unit = props.trendData[0]?.unit ?? '';
    const colors = props.trendData.map(r => ({
        high:    '#ef4444',
        low:     '#eab308',
        normal:  '#22c55e',
        unknown: '#6b7280',
    }[r.status] ?? '#6b7280'));

    return {
        labels,
        datasets: [{
            label: props.availableKeys?.find(k => k.test_key === selectedKey.value)?.test_name ?? selectedKey.value,
            data: props.trendData.map(r => parseFloat(r.value)),
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139,92,246,0.08)',
            pointBackgroundColor: colors,
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 8,
            tension: 0.4,
            fill: true,
        }],
        unit,
    };
});

const deleteReport = (publicId) => {
    if (confirm('Delete this lab report? This cannot be undone.')) {
        router.delete(route('lab-reports.destroy', publicId));
    }
};
</script>

<template>
    <Head title="Lab Reports" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Lab Reports</h2>
                <Link :href="route('lab-reports.create')"
                      class="px-4 py-2 bg-purple-600 text-white rounded-full hover:bg-purple-700 transition shadow text-sm font-medium">
                    + Add Report
                </Link>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px] space-y-6">

                <!-- Trend Chart Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Trend Over Time</h3>
                        <select v-if="availableKeys && availableKeys.length > 0"
                                v-model="selectedKey" @change="changeTrend(selectedKey)"
                                class="text-sm rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 bg-gray-50 max-w-xs">
                            <option value="">— Select a marker to chart —</option>
                            <option v-for="k in availableKeys" :key="k.test_key" :value="k.test_key">
                                {{ k.test_name }} ({{ k.unit }})
                            </option>
                        </select>
                        <p v-else class="text-sm text-gray-400 italic">Add your first report to enable trend charts</p>
                    </div>

                    <div class="h-64 w-full">
                        <HealthChart v-if="trendChartData"
                            :labels="trendChartData.labels"
                            :datasets="trendChartData.datasets"
                            :showLegend="false"
                        />
                        <div v-else class="h-full flex items-center justify-center bg-gray-50 rounded-xl border border-gray-100 border-dashed text-gray-400 text-sm">
                            {{ selectedKey ? 'No data for this marker yet' : 'Select a marker above to view trends' }}
                        </div>
                    </div>
                </div>

                <!-- Reports List -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">All Reports</h3>
                        <span class="text-sm text-gray-400">{{ reports.total ?? 0 }} report{{ reports.total !== 1 ? 's' : '' }}</span>
                    </div>

                    <!-- Empty state -->
                    <div v-if="!reports.data || reports.data.length === 0"
                         class="py-20 text-center text-gray-400">
                        <div class="text-5xl mb-4">🧪</div>
                        <p class="font-semibold text-gray-500 text-lg">No lab reports yet</p>
                        <p class="text-sm mt-1 mb-6">Track lipid profiles, liver function, blood counts and more</p>
                        <Link :href="route('lab-reports.create')"
                              class="inline-flex items-center gap-2 px-5 py-2.5 bg-purple-600 text-white rounded-full text-sm font-semibold hover:bg-purple-700 transition shadow">
                            + Add Your First Report
                        </Link>
                    </div>

                    <div v-else class="divide-y divide-gray-50">
                        <div v-for="report in reports.data" :key="report.public_id">
                            <!-- Report Header Row -->
                            <div class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50/70 transition cursor-pointer"
                                 @click="toggle(report.id)">
                                <!-- Date badge -->
                                <div class="shrink-0 w-14 text-center">
                                    <div class="text-xs font-bold text-purple-600 uppercase">
                                        {{ new Date(report.reported_on).toLocaleString('default', { month: 'short' }) }}
                                    </div>
                                    <div class="text-2xl font-extrabold text-gray-900 leading-none">
                                        {{ new Date(report.reported_on).getDate() }}
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        {{ new Date(report.reported_on).getFullYear() }}
                                    </div>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">
                                        {{ report.panel_name || 'Lab Report' }}
                                    </div>
                                    <div class="text-sm text-gray-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span v-if="report.lab_name">📍 {{ report.lab_name }}</span>
                                        <span v-if="report.ordered_by">👨‍⚕️ {{ report.ordered_by }}</span>
                                        <span class="text-gray-400">{{ totalCount(report) }} marker{{ totalCount(report) !== 1 ? 's' : '' }}</span>
                                    </div>
                                </div>

                                <!-- Abnormal badge -->
                                <div class="shrink-0 flex items-center gap-3">
                                    <span v-if="abnormalCount(report) > 0"
                                          class="text-xs font-semibold px-2.5 py-1 bg-red-100 text-red-700 border border-red-200 rounded-full">
                                        {{ abnormalCount(report) }} abnormal
                                    </span>
                                    <span v-else-if="totalCount(report) > 0"
                                          class="text-xs font-semibold px-2.5 py-1 bg-green-100 text-green-700 border border-green-200 rounded-full">
                                        All normal
                                    </span>
                                    <!-- Chevron -->
                                    <span class="text-gray-400 transition-transform duration-200"
                                          :class="expandedRows.has(report.id) ? 'rotate-180' : ''">▼</span>
                                </div>
                            </div>

                            <!-- Expanded Items -->
                            <div v-if="expandedRows.has(report.id)"
                                 class="px-6 pb-4 pt-0">
                                <div class="bg-gray-50/80 rounded-xl border border-gray-100 overflow-hidden mb-3">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gray-100/60">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Test</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Result</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Reference</th>
                                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="item in report.items" :key="item.id" class="hover:bg-white transition">
                                                <td class="px-4 py-3 font-medium text-gray-800">{{ item.test_name }}</td>
                                                <td class="px-4 py-3 font-mono font-bold text-gray-900">
                                                    {{ parseFloat(item.value).toLocaleString() }}
                                                    <span class="text-xs font-normal text-gray-500 ml-1">{{ item.unit }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-gray-500 text-xs hidden sm:table-cell font-mono">
                                                    <template v-if="item.ref_min !== null || item.ref_max !== null">
                                                        {{ item.ref_min !== null ? parseFloat(item.ref_min).toLocaleString() : '—' }}
                                                        –
                                                        {{ item.ref_max !== null ? parseFloat(item.ref_max).toLocaleString() : '—' }}
                                                        {{ item.unit }}
                                                    </template>
                                                    <span v-else class="text-gray-300">No reference</span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statusBadge(item.status).cls]">
                                                        {{ statusBadge(item.status).label }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Notes -->
                                <p v-if="report.notes_enc" class="text-sm text-gray-600 italic mb-3 px-1">
                                    📝 {{ report.notes_enc }}
                                </p>
                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <Link :href="route('lab-reports.edit', report.public_id)"
                                          class="px-3 py-1.5 text-xs font-semibold text-purple-700 bg-purple-50 border border-purple-200 rounded-lg hover:bg-purple-100 transition">
                                        Edit
                                    </Link>
                                    <button @click="deleteReport(report.public_id)"
                                            class="px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="reports.links && reports.links.length > 3"
                         class="px-6 py-4 border-t border-gray-100 flex justify-center gap-1 flex-wrap">
                        <Link v-for="link in reports.links" :key="link.label"
                              :href="link.url || '#'"
                              v-html="link.label"
                              :class="['px-3 py-1.5 rounded-lg text-xs font-medium transition', link.active ? 'bg-purple-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : '']" />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
