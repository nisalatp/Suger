<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({ reading: Object });

const form = useForm({
    value_raw: props.reading.value_raw,
    unit: props.reading.unit,
    time_of_day: props.reading.time_of_day,
    is_fasting: props.reading.is_fasting,
    insulin_taken: props.reading.insulin_taken,
    meds_taken: props.reading.meds_taken,
    symptoms: props.reading.symptoms_json ?? [],
    notes: props.reading.notes_enc ?? '',
});

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

const submit = () => {
    form.put(route('glucose.update', props.reading.public_id));
};
</script>

<template>
    <Head title="Edit Reading" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('glucose.index')" class="text-gray-400 hover:text-gray-600">← Back</Link>
                <h2 class="text-xl font-semibold text-gray-800">Edit Reading</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4">
                <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-8 space-y-6">
                    <div class="text-center">
                        <div class="flex items-center justify-center gap-3">
                            <input type="number" v-model="form.value_raw" step="0.1" required
                                   class="text-4xl font-bold text-center w-40 border-b-2 border-gray-300 focus:border-purple-500 focus:ring-0 p-2" />
                            <select v-model="form.unit" class="rounded-lg border-gray-300 text-lg">
                                <option value="mg_dL">mg/dL</option>
                                <option value="mmol_L">mmol/L</option>
                            </select>
                        </div>
                    </div>

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

                    <label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer">
                        <input type="checkbox" v-model="form.is_fasting" class="rounded border-gray-300 text-purple-600" />
                        <span class="text-sm font-medium text-gray-700">Fasting reading</span>
                    </label>

                    <div class="flex gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.insulin_taken" class="rounded border-gray-300 text-purple-600" />
                            <span class="text-sm text-gray-700">Insulin taken</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.meds_taken" class="rounded border-gray-300 text-purple-600" />
                            <span class="text-sm text-gray-700">Meds taken</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                    </div>

                    <button type="submit" :disabled="form.processing"
                            class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium disabled:opacity-50">
                        Update Reading
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
