<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({
    performed_at: new Date().toISOString().slice(0, 16),
    activity_type: '',
    duration_minutes: 30,
    calories_burned: '',
    intensity: 'moderate',
    notes: '',
});

const activities = ['Walking', 'Running', 'Cycling', 'Swimming', 'Yoga', 'Strength Training', 'Dancing', 'Other'];

const submit = () => form.post(route('exercise.store'));
</script>

<template>
    <Head title="Log Exercise" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('exercise.index')" class="text-gray-400 hover:text-gray-600">← Back</Link>
                <h2 class="text-xl font-semibold text-gray-800">Log Exercise</h2>
            </div>
        </template>
        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4">
                <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-8 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Activity</label>
                        <div class="flex flex-wrap gap-2 mb-2">
                            <button v-for="a in activities" :key="a" type="button" @click="form.activity_type = a.toLowerCase()"
                                    :class="['px-3 py-1.5 rounded-lg text-sm transition', form.activity_type === a.toLowerCase() ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">{{ a }}</button>
                        </div>
                        <input type="text" v-model="form.activity_type" required placeholder="Or type your own..." class="w-full rounded-lg border-gray-300 text-sm" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (minutes)</label>
                            <input type="number" v-model="form.duration_minutes" min="1" max="1440" required class="w-full rounded-lg border-gray-300" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Intensity</label>
                            <select v-model="form.intensity" class="w-full rounded-lg border-gray-300">
                                <option value="low">Low</option><option value="moderate">Moderate</option><option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">When</label>
                            <input type="datetime-local" v-model="form.performed_at" required class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Calories Burned (Optional)</label>
                            <input type="number" v-model="form.calories_burned" min="1" placeholder="Auto-calculated if blank" class="w-full rounded-lg border-gray-300 text-sm" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium disabled:opacity-50">Log Exercise</button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
