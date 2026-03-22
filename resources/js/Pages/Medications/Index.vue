<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    medications: Array,
    recentEvents: Array,
});

const showAddForm = ref(false);
const doseModal = ref(null);

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
    doseForm.taken_at = new Date().toISOString().slice(0, 16);
};

const submitDose = () => {
    doseForm.post(route('medications.dose', doseModal.value.id), {
        onSuccess: () => { doseModal.value = null; doseForm.reset(); },
    });
};

const deleteMed = (id) => {
    if (confirm('Remove this medication?')) router.delete(route('medications.destroy', id));
};
</script>

<template>
    <Head title="Medications" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Medications</h2>
                <button @click="showAddForm = !showAddForm" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-medium">
                    + Add Medication
                </button>
            </div>
        </template>

        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px]">
                <!-- Add Form -->
                <div v-if="showAddForm" class="bg-white rounded-xl shadow p-6 mb-6">
                    <form @submit.prevent="submitMed" class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                        <div><label class="text-xs text-gray-500">Name</label><input type="text" v-model="addForm.name" required class="w-full rounded border-gray-300 text-sm" /></div>
                        <div><label class="text-xs text-gray-500">Route</label>
                            <select v-model="addForm.route" class="w-full rounded border-gray-300 text-sm">
                                <option value="oral">Oral</option><option value="injection">Injection</option><option value="pump">Pump</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div><label class="text-xs text-gray-500">Dose</label><input type="number" v-model="addForm.dose_value" step="0.1" class="w-full rounded border-gray-300 text-sm" /></div>
                        <div><label class="text-xs text-gray-500">Unit</label><input type="text" v-model="addForm.dose_unit" class="w-full rounded border-gray-300 text-sm" /></div>
                        <div class="flex gap-2">
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" v-model="addForm.is_insulin" class="rounded text-purple-600" />Insulin</label>
                            <button type="submit" :disabled="addForm.processing" class="px-4 py-2 bg-purple-600 text-white rounded text-sm">Save</button>
                        </div>
                    </form>
                </div>

                <!-- Medications List -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                    <div v-for="med in medications" :key="med.id" class="bg-white rounded-xl shadow p-5">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-semibold text-gray-800">{{ med.name }}</h3>
                                <p class="text-sm text-gray-500 capitalize">{{ med.route }} · {{ med.dose_value }} {{ med.dose_unit }}</p>
                                <span v-if="med.is_insulin" class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Insulin</span>
                                <span v-if="!med.active" class="text-xs text-gray-500 ml-2">(Inactive)</span>
                            </div>
                            <div class="flex gap-2">
                                <button @click="openDoseModal(med)" class="px-3 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">Record Dose</button>
                                <button @click="deleteMed(med.id)" class="text-red-400 text-xs hover:underline">Remove</button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">{{ med.events_count }} doses recorded</p>
                    </div>
                    <div v-if="!medications.length" class="md:col-span-2 bg-white rounded-xl shadow p-8 text-center text-gray-400">
                        No medications. Add yours above.
                    </div>
                </div>

                <!-- Recent Events -->
                <div v-if="recentEvents.length" class="bg-white rounded-xl shadow p-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Recent Doses</h3>
                    <div class="space-y-2">
                        <div v-for="ev in recentEvents" :key="ev.id" class="flex justify-between text-sm border-b pb-2">
                            <span class="text-gray-700">{{ ev.medication?.name }}</span>
                            <span class="text-gray-400">{{ new Date(ev.taken_at_utc).toLocaleString() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dose Modal -->
        <div v-if="doseModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="doseModal = null">
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md">
                <h3 class="font-semibold text-gray-800 mb-4">Record Dose — {{ doseModal.name }}</h3>
                <form @submit.prevent="submitDose" class="space-y-4">
                    <div><label class="text-sm text-gray-700">When</label><input type="datetime-local" v-model="doseForm.taken_at" required class="w-full rounded border-gray-300 text-sm" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="text-sm text-gray-700">Dose</label><input type="number" v-model="doseForm.dose_taken_value" step="0.1" class="w-full rounded border-gray-300 text-sm" /></div>
                        <div><label class="text-sm text-gray-700">Unit</label><input type="text" v-model="doseForm.dose_taken_unit" class="w-full rounded border-gray-300 text-sm" /></div>
                    </div>
                    <div><label class="text-sm text-gray-700">Notes</label><textarea v-model="doseForm.notes" rows="2" class="w-full rounded border-gray-300 text-sm"></textarea></div>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="doseModal = null" class="px-4 py-2 border rounded text-sm">Cancel</button>
                        <button type="submit" :disabled="doseForm.processing" class="px-4 py-2 bg-green-600 text-white rounded text-sm">Record</button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
