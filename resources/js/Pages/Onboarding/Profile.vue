<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    user: Object,
    profile: Object,
});

const form = useForm({
    date_of_birth: props.profile?.date_of_birth ?? '',
    gender: props.profile?.gender ?? '',
    height_cm: props.profile?.height_cm ?? '',
    weight_kg: props.profile?.weight_kg ?? '',
    diabetes_type: props.profile?.diabetes_type ?? 'unknown',
    diagnosis_date: props.profile?.diagnosis_date ?? '',
    blood_group: props.profile?.blood_group ?? 'unknown',
});

const step = ref(1);
const totalSteps = 3;

const nextStep = () => { if (step.value < totalSteps) step.value++; };
const prevStep = () => { if (step.value > 1) step.value--; };

const submit = () => {
    form.post(route('onboarding.profile.save'));
};
</script>

<template>
    <Head title="Complete Your Profile" />
    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-2xl px-4">
                <div class="bg-white rounded-xl shadow p-8">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-gray-800">Complete Your Profile</h2>
                        <p class="text-gray-500 mt-2">Step {{ step }} of {{ totalSteps }}</p>
                        <div class="flex gap-2 justify-center mt-4">
                            <div v-for="s in totalSteps" :key="s"
                                 :class="['h-2 w-16 rounded-full', s <= step ? 'bg-purple-600' : 'bg-gray-200']"></div>
                        </div>
                    </div>

                    <form @submit.prevent="submit">
                        <!-- Step 1: Personal Info -->
                        <div v-show="step === 1" class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                                <input type="date" v-model="form.date_of_birth"
                                       class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                <select v-model="form.gender"
                                        class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500">
                                    <option value="">Select...</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Step 2: Physical -->
                        <div v-show="step === 2" class="space-y-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                                    <input type="number" v-model="form.height_cm" step="0.1" min="30" max="300"
                                           class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                                    <input type="number" v-model="form.weight_kg" step="0.1" min="10" max="500"
                                           class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Blood Group</label>
                                <select v-model="form.blood_group"
                                        class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500">
                                    <option value="unknown">Don't know</option>
                                    <option v-for="bg in ['A+','A-','B+','B-','AB+','AB-','O+','O-']" :key="bg" :value="bg">{{ bg }}</option>
                                </select>
                            </div>
                        </div>

                        <!-- Step 3: Diabetes Info -->
                        <div v-show="step === 3" class="space-y-5">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Diabetes Type</label>
                                <select v-model="form.diabetes_type"
                                        class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500">
                                    <option value="unknown">Not sure / Not diagnosed</option>
                                    <option value="type1">Type 1</option>
                                    <option value="type2">Type 2</option>
                                    <option value="gestational">Gestational</option>
                                    <option value="prediabetes">Prediabetes</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Diagnosis Date</label>
                                <input type="date" v-model="form.diagnosis_date"
                                       class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring-purple-500" />
                            </div>
                        </div>

                        <div class="flex justify-between mt-8">
                            <button v-if="step > 1" type="button" @click="prevStep"
                                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                                Back
                            </button>
                            <div v-else></div>

                            <button v-if="step < totalSteps" type="button" @click="nextStep"
                                    class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium">
                                Continue
                            </button>
                            <button v-else type="submit" :disabled="form.processing"
                                    class="px-6 py-2.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium disabled:opacity-50">
                                Save & Continue
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
