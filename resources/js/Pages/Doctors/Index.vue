<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    doctors: { type: Array, default: () => [] },
});

// ── Permission panel ─────────────────────────────────────────────────────────
const expandedPermission = ref(null); // doctor_user_id

const permForms = reactive({});
function getPermForm(doc) {
    if (!permForms[doc.doctor_user_id]) {
        const p = doc.permission;
        permForms[doc.doctor_user_id] = useForm({
            active:      p?.active      ?? true,
            modules:     p?.modules     ?? { glucose: true, meals: false, medications: false, exercise: false },
            format:      p?.format      ?? 'summary',
            period_type: p?.period_type ?? 'last_90',
            period_from: p?.period_from ?? '',
            period_to:   p?.period_to   ?? '',
        });
    }
    return permForms[doc.doctor_user_id];
}

const savePermission = (doc) => {
    const form = getPermForm(doc);
    form.patch(route('doctors.permission', doc.doctor_user_id), {
        onSuccess: () => { expandedPermission.value = null; },
    });
};

const unlink = (doctorUserId) => {
    if (confirm('Remove this doctor from your sharing list?'))
        router.delete(route('doctors.unlink', doctorUserId));
};

// ── Permission card summary helpers ─────────────────────────────────────────
const PERIOD_LABELS = {
    all: 'All time', last_30: 'Last 30 Days', last_90: 'Last 90 Days',
    last_year: '1 Year', custom: 'Custom range',
};
const permSummary = (doc) => {
    if (!doc.permission?.active) return null;
    const mods = Object.entries(doc.permission.modules ?? {})
        .filter(([, v]) => v).map(([k]) => k).join(', ');
    const period = PERIOD_LABELS[doc.permission.period_type] ?? '';
    const fmt = doc.permission.format === 'summary' ? 'Daily Summaries' : 'Raw Records';
    return mods ? `Sharing: ${mods} · ${period} · ${fmt}` : null;
};

// ── Search / Add Doctor modal ─────────────────────────────────────────────────
const showSearch = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);

let searchTimeout = null;
const onSearchInput = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(doSearch, 280);
};

const doSearch = async () => {
    searching.value = true;
    try {
        const res = await axios.get(route('doctors.search'), { params: { q: searchQuery.value } });
        searchResults.value = res.data;
    } finally {
        searching.value = false;
    }
};

const linkDoctor = (doctorUser) => {
    useForm({ doctor_user_id: doctorUser.id }).post(route('doctors.link'), {
        onSuccess: () => {
            showSearch.value = false;
            searchQuery.value = '';
            searchResults.value = [];
        },
    });
};

const openModal = () => {
    showSearch.value = true;
    searchQuery.value = '';
    searchResults.value = [];
    // Load all available doctors immediately
    doSearch();
};

const MODULE_META = {
    glucose:     { label: 'Blood Glucose', icon: '🩸' },
    meals:       { label: 'Meals',         icon: '🍽️' },
    medications: { label: 'Medications',   icon: '💊' },
    exercise:    { label: 'Exercise',      icon: '🏃' },
};
</script>

<template>
    <Head title="Doctors & Data Sharing" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between px-6 w-full">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m-3-3h6M5 3h14A2 2 0 0121 5v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900 leading-tight">Doctors & Data Sharing</h1>
                        <p class="text-xs text-gray-500">Control exactly what each doctor can see about your health</p>
                    </div>
                </div>
                <button @click="openModal"
                    class="flex items-center gap-1.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Doctor
                </button>
            </div>
        </template>

        <div class="w-full pt-[2px] pb-6 px-[5px] space-y-4">

            <!-- Empty state -->
            <div v-if="doctors.length === 0"
                class="flex flex-col items-center justify-center py-24 text-center">
                <div class="w-16 h-16 bg-purple-50 rounded-2xl flex items-center justify-center text-3xl mb-4">🩺</div>
                <h3 class="text-base font-bold text-gray-800 mb-1">No doctors linked yet</h3>
                <p class="text-sm text-gray-500 max-w-xs mb-5">
                    Link a registered doctor to control what health data they can access.
                    Doctors can register by enabling Doctor Mode in their profile.
                </p>
                <button @click="openModal"
                    class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
                    + Add a Doctor
                </button>
            </div>

            <!-- Doctor cards -->
            <div v-for="doc in doctors" :key="doc.doctor_user_id"
                class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Card header -->
                <div class="px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <!-- Avatar -->
                        <div class="shrink-0">
                            <img v-if="doc.avatar_url" :src="doc.avatar_url" :alt="doc.full_name"
                                class="w-10 h-10 rounded-xl object-cover border border-gray-200" />
                            <div v-else class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700">
                                {{ doc.full_name?.charAt(0)?.toUpperCase() }}
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 truncate">{{ doc.full_name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ doc.email }}</p>
                            <!-- Permission summary pill -->
                            <p v-if="permSummary(doc)" class="text-xs text-purple-600 font-medium mt-0.5">{{ permSummary(doc) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 ml-3">
                        <!-- Active badge -->
                        <span v-if="doc.permission?.active" class="flex items-center gap-1 text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span> Sharing Active
                        </span>
                        <span v-else-if="!doc.permission" class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-1 rounded-full">Not Configured</span>
                        <span v-else class="text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-1 rounded-full">Sharing Off</span>

                        <button @click="expandedPermission = expandedPermission === doc.doctor_user_id ? null : doc.doctor_user_id"
                            :class="['px-3 py-1.5 text-xs font-semibold rounded-lg border transition',
                                expandedPermission === doc.doctor_user_id
                                    ? 'bg-purple-600 text-white border-purple-600'
                                    : 'text-purple-600 border-purple-200 hover:bg-purple-50']">
                            {{ expandedPermission === doc.doctor_user_id ? 'Close' : 'Data Access' }}
                        </button>
                        <button @click="unlink(doc.doctor_user_id)"
                            class="px-3 py-1.5 text-xs font-semibold text-red-500 border border-red-100 rounded-lg hover:bg-red-50 transition">
                            Remove
                        </button>
                    </div>
                </div>

                <!-- Expanded permission panel -->
                <div v-if="expandedPermission === doc.doctor_user_id"
                    class="border-t border-gray-100 bg-gray-50/50 px-5 py-4 space-y-4">
                    <p class="text-xs font-semibold text-gray-500">Configure what <span class="text-purple-700">{{ doc.full_name }}</span> can see</p>

                    <div>

                        <!-- Master toggle -->
                        <div class="flex items-center justify-between bg-white rounded-xl p-3 border border-gray-100">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Enable Data Sharing</p>
                                <p class="text-xs text-gray-500">Turn off to stop sharing all data with this doctor</p>
                            </div>
                            <button type="button" @click="getPermForm(doc).active = !getPermForm(doc).active"
                                :class="['relative inline-flex h-6 w-10 shrink-0 cursor-pointer rounded-full transition-colors duration-200',
                                    getPermForm(doc).active ? 'bg-purple-600' : 'bg-gray-200']">
                                <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow mt-1 transition-transform duration-200',
                                    getPermForm(doc).active ? 'translate-x-5' : 'translate-x-1']" />
                            </button>
                        </div>

                        <!-- Health modules -->
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Health Modules</p>
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="(meta, key) in MODULE_META" :key="key" type="button"
                                    @click="getPermForm(doc).modules[key] = !getPermForm(doc).modules[key]"
                                    :class="['flex flex-col items-center p-3 rounded-xl border text-center transition',
                                        getPermForm(doc).modules[key]
                                            ? 'bg-purple-50 border-purple-400 text-purple-800'
                                            : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300']">
                                    <span class="text-2xl mb-1">{{ meta.icon }}</span>
                                    <span class="text-xs font-semibold leading-tight">{{ meta.label }}</span>
                                    <span :class="['text-[10px] font-bold mt-0.5', getPermForm(doc).modules[key] ? 'text-purple-600' : 'text-gray-400']">
                                        {{ getPermForm(doc).modules[key] ? 'Allowed' : 'Hidden' }}
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Data format -->
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Data Format</p>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" @click="getPermForm(doc).format = 'summary'"
                                    :class="['p-3 rounded-xl border text-left transition',
                                        getPermForm(doc).format === 'summary' ? 'bg-purple-50 border-purple-400' : 'bg-white border-gray-200 hover:border-gray-300']">
                                    <p class="text-sm font-semibold" :class="getPermForm(doc).format === 'summary' ? 'text-purple-800' : 'text-gray-700'">Daily Summaries</p>
                                    <p class="text-xs text-gray-400">Aggregated per day</p>
                                </button>
                                <button type="button" @click="getPermForm(doc).format = 'records'"
                                    :class="['p-3 rounded-xl border text-left transition',
                                        getPermForm(doc).format === 'records' ? 'bg-purple-50 border-purple-400' : 'bg-white border-gray-200 hover:border-gray-300']">
                                    <p class="text-sm font-semibold" :class="getPermForm(doc).format === 'records' ? 'text-purple-800' : 'text-gray-700'">Raw Records</p>
                                    <p class="text-xs text-gray-400">Every individual entry</p>
                                </button>
                            </div>
                        </div>

                        <!-- Date range -->
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Date Range</p>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="[val, label] in [['last_30','30 Days'],['last_90','90 Days'],['last_year','1 Year'],['all','All Time'],['custom','Custom']]"
                                    :key="val" type="button"
                                    @click="getPermForm(doc).period_type = val"
                                    :class="['px-3 py-1.5 rounded-lg text-xs font-semibold border transition',
                                        getPermForm(doc).period_type === val
                                            ? 'bg-purple-600 text-white border-purple-600'
                                            : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300']">
                                    {{ label }}
                                </button>
                            </div>
                            <div v-if="getPermForm(doc).period_type === 'custom'" class="flex gap-2 mt-2">
                                <input v-model="getPermForm(doc).period_from" type="date"
                                    class="flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs focus:outline-none focus:border-purple-400" />
                                <input v-model="getPermForm(doc).period_to" type="date"
                                    class="flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-xs focus:outline-none focus:border-purple-400" />
                            </div>
                        </div>

                        <!-- Save -->
                        <div class="flex gap-2 pt-1">
                            <button type="button" @click="savePermission(doc)"
                                :disabled="getPermForm(doc).processing"
                                class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition disabled:opacity-60">
                                {{ getPermForm(doc).processing ? 'Saving…' : 'Save Permissions' }}
                            </button>
                            <button type="button" @click="expandedPermission = null"
                                class="text-gray-500 hover:text-gray-700 text-sm font-semibold px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Add Doctor Search Modal ──────────────────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200" enter-from-class="opacity-0"
                leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-if="showSearch" class="fixed inset-0 z-50 flex items-start justify-center pt-24 px-4"
                    @click.self="showSearch = false">
                    <!-- Backdrop -->
                    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" @click="showSearch = false" />

                    <!-- Modal -->
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden"
                        style="max-height: 70vh;">
                        <div class="px-5 pt-5 pb-3 border-b border-gray-100">
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-base font-bold text-gray-900">Find a Doctor</h2>
                                <button @click="showSearch = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Search input -->
                            <div class="relative">
                                <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 0z" />
                                </svg>
                                <input v-model="searchQuery" @input="onSearchInput" type="text"
                                    placeholder="Search by name or email…"
                                    class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:outline-none focus:border-purple-400" />
                                <div v-if="searching" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin w-4 h-4 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Results list -->
                        <div class="overflow-y-auto" style="max-height: 50vh;">
                            <!-- No results -->
                            <div v-if="!searching && searchResults.length === 0" class="py-10 text-center">
                                <p class="text-3xl mb-2">🩺</p>
                                <p class="text-sm font-semibold text-gray-600">No doctors found</p>
                                <p class="text-xs text-gray-400 mt-1">Doctors must enable Doctor Mode in their profile to appear here</p>
                            </div>

                            <!-- Doctor results -->
                            <div v-for="result in searchResults" :key="result.id"
                                class="flex items-center gap-3 px-5 py-3 hover:bg-purple-50 transition cursor-pointer border-b border-gray-50 last:border-0"
                                @click="linkDoctor(result)">
                                <div class="shrink-0">
                                    <img v-if="result.avatar_url" :src="result.avatar_url" :alt="result.name"
                                        class="w-9 h-9 rounded-xl object-cover" />
                                    <div v-else class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-700">
                                        {{ result.name?.charAt(0)?.toUpperCase() }}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ result.name }}</p>
                                    <p class="text-xs text-gray-400 truncate">{{ result.email }}</p>
                                </div>
                                <div class="shrink-0 flex items-center gap-1 text-xs font-semibold text-purple-600 bg-purple-50 px-2.5 py-1 rounded-full border border-purple-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Add
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
