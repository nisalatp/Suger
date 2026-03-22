<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import AdherenceChart from '@/Components/Charts/AdherenceChart.vue';
import CalendarWidget from '@/Components/CalendarWidget.vue';

const props = defineProps({
    medications: Array,
    events: Object,
    filters: Object,
    chartData: Array,
    alerts: Array,
    stats: Object,
});

const showAddForm = ref(false);
const showHistory = ref(false);
const doseModal = ref(null);

const timeRanges = [
    { label: 'D', value: 'today' },
    { label: 'W', value: '1week' },
    { label: 'M', value: '1month' },
    { label: '3M', value: '3month' },
    { label: 'Y', value: '1year' },
    { label: '5Y', value: '5year' },
];

const selectedRange = ref(props.filters.chart_range || '1week');

const updateChartRange = (range) => {
    selectedRange.value = range;
    router.get(route('medications.index'), { ...props.filters, chart_range: range }, { preserveState: true, preserveScroll: true });
};

const addForm = useForm({
    name: '',
    route: 'oral',
    dose_value: '',
    dose_unit: 'mg',
    is_insulin: false,
});

const doseForm = useForm({
    taken_at: new Date().toISOString().slice(0, 16),
    dose_taken_value: '',
    dose_taken_unit: '',
    notes: '',
});

const submitMed = () => {
    addForm.post(route('medications.store'), {
        onSuccess: () => { showAddForm.value = false; addForm.reset(); },
    });
};

const openDoseModal = (med) => {
    doseModal.value = med;
    doseForm.dose_taken_value = med.dose_value;
    doseForm.dose_taken_unit = med.dose_unit;
    // set to current local time in proper iso datetime format
    const nowLocal = new Date();
    // adjust for timezone offset to get local string
    nowLocal.setMinutes(nowLocal.getMinutes() - nowLocal.getTimezoneOffset());
    doseForm.taken_at = nowLocal.toISOString().slice(0, 16);
};

const submitDose = () => {
    doseForm.post(route('medications.dose', doseModal.value.id), {
        onSuccess: () => { doseModal.value = null; doseForm.reset(); },
    });
};

const deleteMed = (id) => {
    if (confirm('Remove this medication?')) router.delete(route('medications.destroy', id));
};

// Map chart info
const chartLabels = computed(() => props.chartData.map(d => d.date));
const chartDoses = computed(() => props.chartData.map(d => d.doses));
</script>

<template>
    <Head title="Medications" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center w-full px-[5px]">
                <h2 class="text-xl font-semibold text-gray-800">Medications & Adherence</h2>
                <div class="flex items-center gap-4">
                    <button @click="showAddForm = !showAddForm" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-medium">
                        + Add Medication
                    </button>
                    <button class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-purple-600 shadow hover:bg-purple-50 transition border border-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px]">

                <!-- Quick Add Form -->
                <div v-if="showAddForm" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                    <form @submit.prevent="submitMed" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                        <div><label class="text-xs text-gray-500">Name</label><input type="text" v-model="addForm.name" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500" /></div>
                        <div><label class="text-xs text-gray-500">Route</label>
                            <select v-model="addForm.route" class="w-full rounded-lg border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500">
                                <option value="oral">Oral</option><option value="injection">Injection</option><option value="pump">Pump</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div><label class="text-xs text-gray-500">Dose</label><input type="number" v-model="addForm.dose_value" step="0.1" class="w-full rounded-lg border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500" /></div>
                        <div><label class="text-xs text-gray-500">Unit</label><input type="text" v-model="addForm.dose_unit" class="w-full rounded-lg border-gray-300 text-sm focus:ring-purple-500 focus:border-purple-500" /></div>
                        <div class="flex gap-2">
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" v-model="addForm.is_insulin" class="rounded text-purple-600 focus:ring-purple-500" />Insulin</label>
                            <button type="submit" :disabled="addForm.processing" class="w-full py-2 bg-purple-600 text-white rounded-lg text-sm hover:bg-purple-700 transition">Save Med</button>
                        </div>
                    </form>
                </div>

                <!-- Main Chart & Alerts Row -->
                <div class="flex flex-col lg:flex-row gap-6 mb-6">
                    <!-- Dynamic Top Chart Area -->
                    <div :class="[alerts.length > 0 ? 'lg:w-10/12' : 'w-full']" class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100 transition-all duration-300">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                            <div>
                                <h3 class="text-xs font-semibold text-gray-400 tracking-wider uppercase mb-1">Doses Today</h3>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-bold text-gray-900">{{ stats.today_doses }}</span>
                                    <span class="text-sm font-medium text-gray-500">logged</span>
                                </div>
                            </div>
                            
                            <div class="mt-4 md:mt-0 flex flex-wrap items-center gap-3">
                                <!-- Time Scale Filters -->
                                <div class="flex bg-gray-100/80 p-1 rounded-lg border border-gray-200/50">
                                    <button 
                                        v-for="range in timeRanges" 
                                        :key="range.value"
                                        @click="updateChartRange(range.value)"
                                        :class="['px-3 py-1 text-xs font-medium rounded-md transition-colors', selectedRange === range.value ? 'bg-white text-gray-900 shadow-sm ring-1 ring-black/5' : 'text-gray-500 hover:text-gray-700']"
                                    >
                                        {{ range.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Vue-Chartjs Instance -->
                        <div class="h-[250px] w-full">
                            <AdherenceChart :labels="chartLabels" :dosesData="chartDoses" />
                        </div>
                    </div>

                    <!-- Alerts side panel -->
                    <div v-if="alerts.length > 0" class="lg:w-2/12 space-y-3">
                        <div 
                            v-for="(alert, index) in alerts" 
                            :key="index"
                            :class="[
                                'rounded-2xl p-4 shadow-sm h-full flex flex-col justify-center border', 
                                alert.type === 'red' ? 'bg-red-50 border-red-100' : 'bg-amber-50 border-amber-100'
                            ]"
                        >
                            <div class="flex items-start gap-3">
                                <div :class="['w-2 h-2 rounded-full mt-1.5 shrink-0', alert.type === 'red' ? 'bg-red-500' : 'bg-amber-500']"></div>
                                <div>
                                    <h4 :class="['font-semibold text-sm', alert.type === 'red' ? 'text-red-900' : 'text-amber-900']">{{ alert.message }}</h4>
                                    <p :class="['text-xs mt-1 font-medium', alert.type === 'red' ? 'text-red-700' : 'text-amber-700']">{{ alert.time }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Middle Row: Active Meds & Calendar Split -->
                <div class="flex flex-col lg:flex-row gap-6 mb-6">
                    <!-- Active Medications List (1/3) -->
                    <div class="lg:w-1/3 bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col h-[400px]">
                        <div class="p-5 border-b border-gray-100 shrink-0">
                            <h3 class="font-semibold text-gray-900">Active Medications</h3>
                        </div>
                        <div class="p-0 overflow-y-auto grow">
                            <div v-if="medications.length" class="divide-y divide-gray-50">
                                <div v-for="med in medications" :key="med.id" class="p-4 hover:bg-gray-50/50 transition-colors group">
                                    <div class="flex justify-between items-center">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 border border-purple-100 bg-purple-50 text-purple-500">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900 text-sm flex items-center gap-2">
                                                    {{ med.name }}
                                                    <span v-if="med.is_insulin" class="text-[10px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full ring-1 ring-blue-100">Insulin</span>
                                                    <span v-if="!med.active" class="text-[10px] text-gray-500">(Inactive)</span>
                                                </h4>
                                                <p class="text-xs text-gray-500 capitalize mt-0.5">{{ med.route }} · {{ med.dose_value }} {{ med.dose_unit }}</p>
                                            </div>
                                        </div>
                                        <div class="md:opacity-0 md:group-hover:opacity-100 transition-opacity flex items-center gap-2">
                                            <button @click="openDoseModal(med)" class="px-2.5 py-1.5 text-xs font-medium text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors">
                                                Log Dose
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="h-full flex flex-col items-center justify-center p-8 text-center">
                                <span class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 mb-3"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg></span>
                                <p class="text-sm font-medium text-gray-500">No active medications.</p>
                                <p class="text-xs text-gray-400 mt-1">Add a medication to track adherence.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Calendar Widget (2/3) -->
                    <div class="lg:w-2/3 bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col h-[400px]">
                        <h3 class="font-semibold text-gray-800 mb-4">Adherence Calendar</h3>
                        <div class="grow h-full overflow-hidden">
                            <CalendarWidget :monthlyData="chartData" />
                        </div>
                    </div>
                </div>

                <!-- Collapsible Details Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mt-6">
                    <button @click="showHistory = !showHistory" class="w-full px-6 py-4 flex items-center justify-between bg-gray-50/50 hover:bg-gray-50 transition-colors">
                        <span class="font-semibold text-gray-700">Dose History ({{ events.total }})</span>
                        <svg class="w-5 h-5 text-gray-400 transform transition-transform" :class="{'rotate-180': showHistory}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    
                    <div v-show="showHistory" class="border-t border-gray-100">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm whitespace-nowrap">
                                <thead>
                                    <tr class="bg-gray-50/50 border-b border-gray-100">
                                        <th class="px-6 py-4 font-semibold text-gray-600">Date & Time</th>
                                        <th class="px-6 py-4 font-semibold text-gray-600">Medication</th>
                                        <th class="px-6 py-4 font-semibold text-gray-600">Dose</th>
                                        <th class="px-6 py-4 font-semibold text-gray-600">Notes</th>
                                        <th class="px-6 py-4 font-semibold text-gray-600 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="event in events.data" :key="event.id" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="px-6 py-4 text-gray-700">
                                            {{ new Date(event.taken_at_utc).toLocaleString(undefined, {
                                                month: 'short', day: 'numeric', year: 'numeric',
                                                hour: 'numeric', minute: '2-digit'
                                            }) }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-medium text-gray-900">{{ event.medication?.name }}</div>
                                            <div class="text-xs text-gray-500 capitalize">{{ event.medication?.route }}</div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="font-medium text-gray-900">{{ event.dose_taken_value }}</span>
                                            <span class="text-gray-500 ml-1">{{ event.dose_taken_unit }}</span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 truncate max-w-xs">
                                            {{ event.notes_enc || '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <Link :href="route('medications.events.destroy', event.id)" method="delete" as="button" class="text-xs font-medium text-red-600 hover:text-red-900 transition-colors">Remove</Link>
                                        </td>
                                    </tr>
                                    <tr v-if="events.data.length === 0">
                                        <td colspan="5" class="px-6 py-8 text-center text-gray-400">No history found for this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between" v-if="events.links?.length > 3">
                            <div class="flex flex-wrap gap-1">
                                <template v-for="(link, i) in events.links" :key="i">
                                    <Link 
                                        v-if="link.url"
                                        :href="link.url"
                                        v-html="link.label"
                                        class="px-3 py-1 text-sm rounded-lg border transition-colors"
                                        :class="link.active ? 'bg-purple-50 text-purple-700 border-purple-200 font-medium' : 'text-gray-600 border-gray-200 hover:bg-gray-50'"
                                        preserve-scroll
                                    />
                                    <span
                                        v-else
                                        v-html="link.label"
                                        class="px-3 py-1 text-sm rounded-lg border border-gray-100 text-gray-300 cursor-not-allowed"
                                    />
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Dose Modal -->
        <div v-if="doseModal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm flex items-center justify-center z-50 p-4 transition-all" @click.self="doseModal = null">
            <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-md transform transition-all border border-gray-100">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-semibold text-gray-900">Record Dose</h3>
                    <button @click="doseModal = null" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                </div>
                
                <div class="mb-5 p-4 rounded-xl bg-purple-50/50 border border-purple-100/50">
                    <div class="font-medium text-purple-900">{{ doseModal.name }}</div>
                    <div class="text-sm text-purple-700 mt-0.5 capitalize">{{ doseModal.route }}</div>
                </div>

                <form @submit.prevent="submitDose" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">When did you take it?</label>
                        <input type="datetime-local" v-model="doseForm.taken_at" required class="w-full rounded-xl border-gray-200 text-sm focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-shadow" />
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Dose</label>
                            <input type="number" v-model="doseForm.dose_taken_value" step="0.1" class="w-full rounded-xl border-gray-200 text-sm focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-shadow" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Unit</label>
                            <input type="text" v-model="doseForm.dose_taken_unit" class="w-full rounded-xl border-gray-200 text-sm focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-shadow" />
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes (Optional)</label>
                        <textarea v-model="doseForm.notes" rows="2" class="w-full rounded-xl border-gray-200 text-sm focus:ring-purple-500 focus:border-purple-500 shadow-sm transition-shadow resize-none" placeholder="E.g. Taken after food..."></textarea>
                    </div>
                    
                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="doseModal = null" class="w-1/3 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-100 transition-colors">Cancel</button>
                        <button type="submit" :disabled="doseForm.processing" class="w-2/3 py-2.5 bg-purple-600 text-white rounded-xl text-sm font-medium hover:bg-purple-700 transition-colors shadow-sm focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
