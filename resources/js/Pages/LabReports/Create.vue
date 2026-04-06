<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';

// ── Predefined test catalog ────────────────────────────────────────────────
const TEST_CATALOG = [
    // Lipid Profile
    { key: 'total-cholesterol',  name: 'Total Cholesterol',  unit: 'mg/dL', refMin: null, refMax: 200 },
    { key: 'ldl-cholesterol',    name: 'LDL Cholesterol',    unit: 'mg/dL', refMin: null, refMax: 100 },
    { key: 'hdl-cholesterol',    name: 'HDL Cholesterol',    unit: 'mg/dL', refMin: 40,   refMax: null },
    { key: 'triglycerides',      name: 'Triglycerides',      unit: 'mg/dL', refMin: null, refMax: 150 },
    { key: 'vldl',               name: 'VLDL',               unit: 'mg/dL', refMin: null, refMax: 30  },
    // Liver Function
    { key: 'alt-sgpt',           name: 'SGPT (ALT)',         unit: 'U/L',   refMin: 7,    refMax: 56  },
    { key: 'ast-sgot',           name: 'SGOT (AST)',         unit: 'U/L',   refMin: 10,   refMax: 40  },
    { key: 'alp',                name: 'ALP',                unit: 'U/L',   refMin: 44,   refMax: 147 },
    { key: 'ggt',                name: 'GGT',                unit: 'U/L',   refMin: 9,    refMax: 48  },
    { key: 'total-bilirubin',    name: 'Total Bilirubin',    unit: 'mg/dL', refMin: 0.2,  refMax: 1.2 },
    { key: 'albumin',            name: 'Albumin',            unit: 'g/dL',  refMin: 3.4,  refMax: 5.4 },
    // Kidney Function
    { key: 'creatinine',         name: 'Creatinine',         unit: 'mg/dL', refMin: 0.7,  refMax: 1.3 },
    { key: 'egfr',               name: 'eGFR',               unit: 'mL/min',refMin: 60,   refMax: null},
    { key: 'bun',                name: 'BUN',                unit: 'mg/dL', refMin: 7,    refMax: 25  },
    { key: 'uric-acid',          name: 'Uric Acid',          unit: 'mg/dL', refMin: 3.5,  refMax: 7.2 },
    // Diabetes
    { key: 'hba1c',              name: 'HbA1c',              unit: '%',     refMin: null, refMax: 5.7 },
    { key: 'fasting-glucose',    name: 'Fasting Glucose',    unit: 'mg/dL', refMin: 70,   refMax: 100 },
    // Full Blood Count
    { key: 'haemoglobin',        name: 'Haemoglobin',        unit: 'g/dL',  refMin: 12,   refMax: 17.5},
    { key: 'wbc',                name: 'WBC',                unit: '×10³/µL', refMin: 4.5, refMax: 11.0},
    { key: 'platelets',          name: 'Platelets',          unit: '×10³/µL', refMin: 150, refMax: 400 },
    { key: 'rbc',                name: 'RBC',                unit: '×10⁶/µL', refMin: 4.5, refMax: 5.9 },
    { key: 'hct',                name: 'Haematocrit (HCT)',  unit: '%',     refMin: 38.3, refMax: 48.6},
    // Thyroid
    { key: 'tsh',                name: 'TSH',                unit: 'mIU/L', refMin: 0.4,  refMax: 4.0 },
    { key: 'ft4',                name: 'Free T4',            unit: 'ng/dL', refMin: 0.8,  refMax: 1.8 },
    { key: 'ft3',                name: 'Free T3',            unit: 'pg/mL', refMin: 2.3,  refMax: 4.2 },
    // Custom
    { key: '_custom',            name: 'Custom Test…',       unit: '',      refMin: null, refMax: null},
];

const GROUPS = [
    { label: 'Lipid Profile',    keys: ['total-cholesterol','ldl-cholesterol','hdl-cholesterol','triglycerides','vldl'] },
    { label: 'Liver Function',   keys: ['alt-sgpt','ast-sgot','alp','ggt','total-bilirubin','albumin'] },
    { label: 'Kidney Function',  keys: ['creatinine','egfr','bun','uric-acid'] },
    { label: 'Diabetes',         keys: ['hba1c','fasting-glucose'] },
    { label: 'Full Blood Count', keys: ['haemoglobin','wbc','platelets','rbc','hct'] },
    { label: 'Thyroid',          keys: ['tsh','ft4','ft3'] },
    { label: 'Custom',           keys: ['_custom'] },
];

// ── Form state ─────────────────────────────────────────────────────────────
const form = useForm({
    reported_on: new Date().toISOString().slice(0, 10),
    lab_name:    '',
    panel_name:  '',
    ordered_by:  '',
    notes:       '',
    items:       [],
});

// Search/select panel
const showPicker = ref(false);
const pickerSearch = ref('');

const filteredCatalog = computed(() => {
    const q = pickerSearch.value.toLowerCase();
    if (!q) return TEST_CATALOG;
    return TEST_CATALOG.filter(t => t.name.toLowerCase().includes(q) || t.key.includes(q));
});

const addFromCatalog = (test) => {
    if (test.key === '_custom') {
        form.items.push({ test_name: '', test_key: '', value: '', unit: '', ref_min: '', ref_max: '', notes: '' });
    } else {
        // Don't add duplicates
        if (form.items.some(i => i.test_key === test.key)) return;
        form.items.push({
            test_name: test.name,
            test_key:  test.key,
            value:     '',
            unit:      test.unit,
            ref_min:   test.refMin ?? '',
            ref_max:   test.refMax ?? '',
            notes:     '',
        });
    }
    showPicker.value  = false;
    pickerSearch.value = '';
};

const removeItem = (idx) => form.items.splice(idx, 1);

const getStatus = (item) => {
    const v = parseFloat(item.value);
    if (isNaN(v)) return null;
    const mn = item.ref_min !== '' && item.ref_min !== null ? parseFloat(item.ref_min) : null;
    const mx = item.ref_max !== '' && item.ref_max !== null ? parseFloat(item.ref_max) : null;
    if (mn !== null && v < mn) return 'low';
    if (mx !== null && v > mx) return 'high';
    if (mn !== null || mx !== null) return 'normal';
    return null;
};

const statusClass = (status) => ({
    low:    'bg-yellow-100 text-yellow-800 border border-yellow-300',
    normal: 'bg-green-100 text-green-800 border border-green-300',
    high:   'bg-red-100 text-red-800 border border-red-300',
}[status] || '');

const statusLabel = (status) => ({ low: '⬇ Low', normal: '✓ Normal', high: '⬆ High' }[status] || '');

const submit = () => form.post(route('lab-reports.store'));
</script>

<template>
    <Head title="Add Lab Report" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('lab-reports.index')" class="text-gray-400 hover:text-gray-600 transition">← Back</Link>
                <h2 class="text-xl font-semibold text-gray-800">Add Lab Report</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Report Details Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4">Report Details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Report <span class="text-red-500">*</span></label>
                                <input type="date" v-model="form.reported_on" required
                                       class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 text-sm" />
                                <p v-if="form.errors.reported_on" class="text-red-500 text-xs mt-1">{{ form.errors.reported_on }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Panel / Report Name</label>
                                <input type="text" v-model="form.panel_name" maxlength="200" placeholder="e.g. Lipid Profile, Full Blood Count"
                                       class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Laboratory</label>
                                <input type="text" v-model="form.lab_name" maxlength="200" placeholder="e.g. Nawaloka Hospitals"
                                       class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ordered By (Doctor)</label>
                                <input type="text" v-model="form.ordered_by" maxlength="200" placeholder="Dr. …"
                                       class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 text-sm" />
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="3000" placeholder="Optional notes…"
                                      class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 text-sm"></textarea>
                        </div>
                    </div>

                    <!-- Test Results Card -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider">Test Results</h3>
                            <button type="button" @click="showPicker = true"
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 transition">
                                <span class="text-base leading-none">+</span> Add Test
                            </button>
                        </div>

                        <p v-if="form.errors.items" class="text-red-500 text-sm mb-3">{{ form.errors.items }}</p>

                        <div v-if="form.items.length === 0"
                             class="py-12 text-center border-2 border-dashed border-gray-200 rounded-xl text-gray-400">
                            <div class="text-4xl mb-2">🧪</div>
                            <p class="font-medium">No tests added yet</p>
                            <p class="text-sm mt-1">Click "Add Test" to select from the catalog or add a custom test</p>
                        </div>

                        <!-- Items Table -->
                        <div v-else class="space-y-3">
                            <div v-for="(item, idx) in form.items" :key="idx"
                                 class="group relative border border-gray-100 rounded-xl p-4 bg-gray-50/50 hover:bg-gray-50 transition">
                                <div class="grid grid-cols-12 gap-3 items-start">
                                    <!-- Test Name -->
                                    <div class="col-span-12 sm:col-span-4">
                                        <label class="block text-xs text-gray-500 mb-1">Test Name</label>
                                        <input type="text" v-model="item.test_name" required placeholder="Test name"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500 bg-white" />
                                    </div>
                                    <!-- Value -->
                                    <div class="col-span-4 sm:col-span-2">
                                        <label class="block text-xs text-gray-500 mb-1">Value</label>
                                        <input type="number" v-model="item.value" step="any" required placeholder="0.0"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500 bg-white font-mono" />
                                    </div>
                                    <!-- Unit -->
                                    <div class="col-span-4 sm:col-span-2">
                                        <label class="block text-xs text-gray-500 mb-1">Unit</label>
                                        <input type="text" v-model="item.unit" placeholder="mg/dL"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500 bg-white" />
                                    </div>
                                    <!-- Ref Range -->
                                    <div class="col-span-4 sm:col-span-2">
                                        <label class="block text-xs text-gray-500 mb-1">Ref Min</label>
                                        <input type="number" v-model="item.ref_min" step="any" placeholder="—"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 bg-white font-mono" />
                                    </div>
                                    <div class="col-span-4 sm:col-span-2">
                                        <label class="block text-xs text-gray-500 mb-1">Ref Max</label>
                                        <input type="number" v-model="item.ref_max" step="any" placeholder="—"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 bg-white font-mono" />
                                    </div>
                                </div>

                                <!-- Status badge + remove -->
                                <div class="flex items-center justify-between mt-3">
                                    <span v-if="getStatus(item)"
                                          :class="['text-xs font-semibold px-2 py-0.5 rounded-full', statusClass(getStatus(item))]">
                                        {{ statusLabel(getStatus(item)) }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400 italic">Enter a value to see status</span>
                                    <button type="button" @click="removeItem(idx)"
                                            class="text-red-400 hover:text-red-600 text-xs font-medium transition px-2 py-1 rounded-lg hover:bg-red-50">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing || form.items.length === 0"
                            class="w-full py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition font-semibold text-sm shadow disabled:opacity-50">
                        {{ form.processing ? 'Saving…' : 'Save Lab Report' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Test Picker Modal -->
        <Teleport to="body">
            <div v-if="showPicker" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
                 @click.self="showPicker = false">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between p-5 border-b border-gray-100">
                        <h3 class="font-bold text-gray-900">Select Test</h3>
                        <button @click="showPicker = false" class="text-gray-400 hover:text-gray-600 transition rounded-lg p-1 hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="p-4 border-b border-gray-100">
                        <input v-model="pickerSearch" type="text" placeholder="Search tests…" autofocus
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500" />
                    </div>
                    <div class="overflow-y-auto flex-1 p-2">
                        <template v-if="!pickerSearch">
                            <div v-for="group in GROUPS" :key="group.label" class="mb-3">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 mb-1">{{ group.label }}</p>
                                <button v-for="key in group.keys" :key="key"
                                        @click="addFromCatalog(TEST_CATALOG.find(t => t.key === key))"
                                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm hover:bg-purple-50 hover:text-purple-700 transition text-left group">
                                    <span class="font-medium">{{ TEST_CATALOG.find(t => t.key === key)?.name }}</span>
                                    <span class="text-xs text-gray-400 group-hover:text-purple-400">
                                        {{ TEST_CATALOG.find(t => t.key === key)?.unit }}
                                    </span>
                                </button>
                            </div>
                        </template>
                        <template v-else>
                            <button v-for="test in filteredCatalog" :key="test.key"
                                    @click="addFromCatalog(test)"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm hover:bg-purple-50 hover:text-purple-700 transition text-left">
                                <span class="font-medium">{{ test.name }}</span>
                                <span class="text-xs text-gray-400">{{ test.unit }}</span>
                            </button>
                            <p v-if="filteredCatalog.length === 0" class="text-center text-gray-400 text-sm py-6">No tests found</p>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
