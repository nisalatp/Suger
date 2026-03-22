<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    consents: Array,
});

const form = useForm({
    terms: false,
    privacy: false,
    health_processing: false,
});

const submit = () => {
    form.post(route('onboarding.consent.save'));
};
</script>

<template>
    <Head title="Consent" />
    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-2xl px-4">
                <div class="bg-white rounded-xl shadow p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Data Processing Consent</h2>
                    <p class="text-gray-500 mb-8">
                        We need your explicit consent to process your health data. You can withdraw consent at any time.
                    </p>

                    <form @submit.prevent="submit" class="space-y-6">
                        <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input type="checkbox" v-model="form.terms" class="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                            <div>
                                <span class="font-medium text-gray-800">Terms of Service</span>
                                <span class="text-red-500 ml-1">*</span>
                                <p class="text-sm text-gray-500 mt-1">I agree to the terms and conditions of using Suger.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input type="checkbox" v-model="form.privacy" class="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                            <div>
                                <span class="font-medium text-gray-800">Privacy Policy</span>
                                <span class="text-red-500 ml-1">*</span>
                                <p class="text-sm text-gray-500 mt-1">I have read and agree to the privacy policy.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 border rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input type="checkbox" v-model="form.health_processing" class="mt-1 rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                            <div>
                                <span class="font-medium text-gray-800">Health Data Processing</span>
                                <span class="text-red-500 ml-1">*</span>
                                <p class="text-sm text-gray-500 mt-1">I consent to the processing of my health data (blood glucose, medications, etc.) for tracking and reporting purposes.</p>
                            </div>
                        </label>

                        <div v-if="form.errors" class="text-red-500 text-sm">
                            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
                        </div>

                        <button type="submit"
                                :disabled="form.processing || !form.terms || !form.privacy || !form.health_processing"
                                class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium disabled:opacity-50">
                            I Agree — Go to Dashboard
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
