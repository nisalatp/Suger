<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({
    value_raw: '',
    unit: 'mg_dL',
    measured_at: new Date().toISOString().slice(0, 16),
    time_of_day: 'other',
    meal_type: 'unknown',
    is_fasting: false,
    last_meal_at: '',
    last_drink_at: '',
    insulin_taken: false,
    meds_taken: false,
    symptoms: [],
    notes: '',
});

const symptomOptions = [
    'none', 'shakiness', 'dizziness', 'sweating', 'hunger',
    'headache', 'blurred_vision', 'fatigue', 'nausea', 'irritability',
    'thirst', 'frequent_urination', 'dry_mouth', 'other',
];

const timeOfDayOptions = [
    { value: 'pre_breakfast', label: 'Pre-breakfast' },
    { value: 'post_breakfast', label: 'Post-breakfast' },
    { value: 'pre_lunch', label: 'Pre-lunch' },
    { value: 'post_lunch', label: 'Post-lunch' },
    { value: 'pre_dinner', label: 'Pre-dinner' },
    { value: 'post_dinner', label: 'Post-dinner' },
    { value: 'bedtime', label: 'Bedtime' },
    { value: 'overnight', label: 'Overnight' },
    { value: 'other', label: 'Other' },
];

const toggleSymptom = (symptom) => {
    const idx = form.symptoms.indexOf(symptom);
    if (idx > -1) {
        form.symptoms.splice(idx, 1);
    } else {
        form.symptoms.push(symptom);
    }
};

const submit = () => {
    form.post(route('glucose.store'));
};
</script>

<template>
    <Head title="Add Reading" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('glucose.index')" class="text-gray-400 hover:text-gray-600">← Back</Link>
                <h2 class="text-xl font-semibold text-gray-800">Add Glucose Reading</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4">
                <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-8 space-y-6">
                    <!-- Value Input -->
                    <div class="text-center mb-4">
                        <label class="block text-sm font-medium text-gray-500 mb-2">Blood Glucose Level</label>
                        <div class="flex items-center justify-center gap-3">
                            <input type="number" v-model="form.value_raw" step="0.1" min="0.1" max="800" required
                                   class="text-4xl font-bold text-center w-40 border-b-2 border-gray-300 focus:border-purple-500 focus:ring-0 p-2"
                                   placeholder="0.0" />
                            <select v-model="form.unit" class="rounded-lg border-gray-300 text-lg">
                                <option value="mg_dL">mg/dL</option>
                                <option value="mmol_L">mmol/L</option>
                            </select>
                        </div>
                        <div v-if="form.errors.value_raw" class="text-red-500 text-sm mt-1">{{ form.errors.value_raw }}</div>
                    </div>

                    <!-- Date/Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">When was this taken?</label>
                        <input type="datetime-local" v-model="form.measured_at" required
                               class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500" />
                    </div>

                    <!-- Time of Day Quick Tags -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Time of Day</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="opt in timeOfDayOptions" :key="opt.value" type="button"
                                    @click="form.time_of_day = opt.value"
                                    :class="['px-3 py-1.5 rounded-lg text-sm transition', form.time_of_day === opt.value ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Fasting -->
                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" v-model="form.is_fasting" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                        <span class="text-sm font-medium text-gray-700">Fasting reading</span>
                    </label>

                    <!-- Last meal / drink -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last meal at</label>
                            <input type="datetime-local" v-model="form.last_meal_at"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last drink at</label>
                            <input type="datetime-local" v-model="form.last_drink_at"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-purple-500" />
                        </div>
                    </div>

                    <!-- Insulin / Meds -->
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.insulin_taken" class="rounded border-gray-300 text-purple-600" />
                            <span class="text-sm text-gray-700">Insulin taken</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.meds_taken" class="rounded border-gray-300 text-purple-600" />
                            <span class="text-sm text-gray-700">Meds taken</span>
                        </label>
                    </div>

                    <!-- Symptoms -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Symptoms</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="symptom in symptomOptions" :key="symptom" type="button"
                                    @click="toggleSymptom(symptom)"
                                    :class="['px-3 py-1 rounded-full text-xs transition capitalize', form.symptoms.includes(symptom) ? 'bg-purple-100 text-purple-700 border border-purple-300' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">
                                {{ symptom.replace(/_/g, ' ') }}
                            </button>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="form.notes" rows="2" maxlength="2000"
                                  class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500 text-sm"
                                  placeholder="Optional notes..."></textarea>
                    </div>

                    <button type="submit" :disabled="form.processing"
                            class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium disabled:opacity-50">
                        Save Reading
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
