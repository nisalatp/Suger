<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    profile: { type: Object, default: () => ({}) },
});

const user = computed(() => usePage().props.auth.user);

// ── Health profile form ──────────────────────────────────────────────────────
const form = useForm({
    date_of_birth:   props.profile?.date_of_birth   ?? '',
    gender:          props.profile?.gender           ?? '',
    height_cm:       props.profile?.height_cm        ?? '',
    weight_kg:       props.profile?.weight_kg        ?? '',
    diabetes_type:   props.profile?.diabetes_type    ?? 'unknown',
    diagnosis_date:  props.profile?.diagnosis_date   ?? '',
    blood_group:     props.profile?.blood_group      ?? 'unknown',
    family_history_summary_enc: props.profile?.family_history_summary_enc ?? '',
});

const save = () => form.patch(route('profile.update'), { preserveScroll: true });

// ── BMI helper ───────────────────────────────────────────────────────────────
const bmi = computed(() => {
    const h = parseFloat(form.height_cm);
    const w = parseFloat(form.weight_kg);
    if (!h || !w || h < 30) return null;
    return (w / ((h / 100) ** 2)).toFixed(1);
});
const bmiLabel = computed(() => {
    if (!bmi.value) return '';
    const v = parseFloat(bmi.value);
    if (v < 18.5) return 'Underweight';
    if (v < 25)   return 'Normal weight';
    if (v < 30)   return 'Overweight';
    return 'Obese';
});
const bmiColor = computed(() => {
    if (!bmi.value) return 'text-gray-400';
    const v = parseFloat(bmi.value);
    if (v < 18.5) return 'text-blue-500';
    if (v < 25)   return 'text-green-600';
    if (v < 30)   return 'text-yellow-600';
    return 'text-red-600';
});

// ── Doctor mode form ─────────────────────────────────────────────────────────
const togglingDoctor = ref(false);
const toggleDoctor = () => {
    togglingDoctor.value = true;
    const urlKey = user.value.is_doctor ? 'profile.disable_doctor' : 'profile.enable_doctor';
    useForm({}).post(route(urlKey), {
        onFinish: () => { togglingDoctor.value = false; },
    });
};

const GENDERS    = [{ v: 'male', l: 'Male' }, { v: 'female', l: 'Female' }, { v: 'other', l: 'Other' }];
const DX_TYPES   = [
    { v: 'type1',       l: 'Type 1' },
    { v: 'type2',       l: 'Type 2' },
    { v: 'gestational', l: 'Gestational' },
    { v: 'prediabetes', l: 'Pre-diabetes' },
    { v: 'other',       l: 'Other' },
    { v: 'unknown',     l: 'Prefer not to say' },
];
const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'];
</script>

<template>
    <Head title="Profile Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3 px-6">
                <div class="w-9 h-9 rounded-xl bg-purple-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-gray-900 leading-tight">Profile Settings</h1>
                    <p class="text-xs text-gray-500">Manage your personal and health information</p>
                </div>
            </div>
        </template>

        <div class="w-full pt-[2px] pb-6 px-[5px] space-y-6">

            <!-- ── Account Identity Card ───────────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center gap-2">
                    <span class="text-base font-bold text-gray-900">Account</span>
                    <span class="text-xs bg-blue-50 text-blue-600 font-semibold px-2 py-0.5 rounded-full">Google Login</span>
                </div>
                <div class="px-6 py-5 flex items-center gap-4">
                    <!-- Avatar -->
                    <div class="shrink-0">
                        <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name"
                            class="w-16 h-16 rounded-2xl object-cover border border-gray-200" />
                        <div v-else class="w-16 h-16 rounded-2xl bg-purple-100 flex items-center justify-center text-2xl font-bold text-purple-700">
                            {{ user.name?.charAt(0)?.toUpperCase() }}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-lg font-bold text-gray-900 truncate">{{ user.name }}</p>
                        <p class="text-sm text-gray-500 truncate">{{ user.email }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Signed in with Google · passwords are managed by Google</p>
                    </div>
                </div>
            </div>

            <!-- ── Doctor Mode Card ────────────────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50">
                    <span class="text-base font-bold text-gray-900">Doctor Mode</span>
                </div>
                <div class="px-6 py-5 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div :class="['w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0',
                            user.is_doctor ? 'bg-blue-50' : 'bg-gray-50']">
                            🩺
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">
                                {{ user.is_doctor ? 'Doctor mode is active' : 'Enable Doctor Mode' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ user.is_doctor
                                    ? 'You can view your patients via My Patients in the sidebar.'
                                    : 'Activate to access the My Patients portal and view shared health data.' }}
                            </p>
                        </div>
                    </div>
                    <!-- Toggle switch -->
                    <button
                        @click="toggleDoctor"
                        :disabled="togglingDoctor"
                        :class="['relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full transition-colors duration-200 ease-in-out focus:outline-none',
                            user.is_doctor ? 'bg-blue-600' : 'bg-gray-200',
                            togglingDoctor && 'opacity-50']"
                        role="switch" :aria-checked="user.is_doctor">
                        <span :class="['pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition-transform duration-200 ease-in-out mt-1',
                            user.is_doctor ? 'translate-x-6' : 'translate-x-1']" />
                    </button>
                </div>
                <div v-if="user.is_doctor" class="px-6 pb-4">
                    <p class="text-xs text-blue-600 bg-blue-50 rounded-lg px-3 py-2">
                        💡 Patients link to you automatically when they add your email address in their Doctors section.
                    </p>
                </div>
            </div>

            <!-- ── Health Profile Form ─────────────────────────────────────── -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50">
                    <span class="text-base font-bold text-gray-900">Health Profile</span>
                    <p class="text-xs text-gray-400 mt-0.5">Used to personalise your glucose targets and reports</p>
                </div>
                <form @submit.prevent="save" class="px-6 py-5 space-y-5">

                    <!-- Row: Gender + DOB -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Gender</label>
                            <select v-model="form.gender"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none">
                                <option value="">Prefer not to say</option>
                                <option v-for="g in GENDERS" :key="g.v" :value="g.v">{{ g.l }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date of Birth</label>
                            <input v-model="form.date_of_birth" type="date"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none" />
                        </div>
                    </div>

                    <!-- Row: Height + Weight + BMI -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Height (cm)</label>
                            <input v-model="form.height_cm" type="number" min="30" max="300" step="0.1" placeholder="e.g. 170"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Weight (kg)</label>
                            <input v-model="form.weight_kg" type="number" min="10" max="500" step="0.1" placeholder="e.g. 70"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">BMI</label>
                            <div class="w-full rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5 flex items-center gap-2">
                                <span v-if="bmi" :class="['text-sm font-bold', bmiColor]">{{ bmi }}</span>
                                <span v-if="bmi" :class="['text-xs', bmiColor]">{{ bmiLabel }}</span>
                                <span v-else class="text-xs text-gray-400">auto-calculated</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row: Diabetes type + Diagnosis date -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Diabetes Type</label>
                            <select v-model="form.diabetes_type"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none">
                                <option v-for="d in DX_TYPES" :key="d.v" :value="d.v">{{ d.l }}</option>
                            </select>
                            <p v-if="form.errors.diabetes_type" class="text-xs text-red-500 mt-1">{{ form.errors.diabetes_type }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Diagnosis Date</label>
                            <input v-model="form.diagnosis_date" type="date"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none" />
                        </div>
                    </div>

                    <!-- Blood group -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Blood Group</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="bg in BLOOD_GROUPS" :key="bg" type="button"
                                @click="form.blood_group = bg"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors',
                                    form.blood_group === bg
                                        ? 'bg-red-500 text-white border-red-500'
                                        : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-red-300 hover:text-red-500']">
                                {{ bg === 'unknown' ? 'Unknown' : bg }}
                            </button>
                        </div>
                    </div>

                    <!-- Family history -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Family History Notes <span class="font-normal text-gray-400">(optional, stored encrypted)</span></label>
                        <textarea v-model="form.family_history_summary_enc" rows="3" placeholder="Any relevant family medical history…"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:border-purple-400 focus:ring-2 focus:ring-purple-100 focus:outline-none resize-none" />
                    </div>

                    <!-- Save Button -->
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 disabled:opacity-60 transition">
                            {{ form.processing ? 'Saving…' : 'Save Health Profile' }}
                        </button>
                        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0"
                            enter-active-class="transition duration-300" leave-active-class="transition duration-300">
                            <p v-if="form.wasSuccessful" class="text-sm text-green-600 font-medium">✓ Saved!</p>
                        </Transition>
                    </div>
                </form>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
