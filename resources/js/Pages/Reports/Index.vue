<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({ exports: Object });

const showWizard = ref(false);
const form = useForm({
    format: 'csv',
    range_start: new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10),
    range_end: new Date().toISOString().slice(0, 10),
    include_sections: {
        profile_summary: true,
        glucose_trends: true,
        meals_summary: true,
        medications: true,
    },
});

const submit = () => {
    form.post(route('reports.store'), { onSuccess: () => { showWizard.value = false; } });
};
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Reports & Exports</h2>
                <button @click="showWizard = !showWizard" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-medium">+ New Export</button>
            </div>
        </template>
        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px]">
                <!-- Export Wizard -->
                <div v-if="showWizard" class="bg-white rounded-xl shadow p-6 mb-6">
                    <h3 class="font-semibold text-gray-800 mb-4">Create Export</h3>
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="text-xs text-gray-500">Format</label>
                                <select v-model="form.format" class="w-full rounded border-gray-300 text-sm">
                                    <option value="csv">CSV</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">From</label>
                                <input type="date" v-model="form.range_start" required class="w-full rounded border-gray-300 text-sm" />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">To</label>
                                <input type="date" v-model="form.range_end" required class="w-full rounded border-gray-300 text-sm" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-2">Include Sections</label>
                            <div class="flex flex-wrap gap-3">
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="form.include_sections.profile_summary" class="rounded text-purple-600" />Profile Summary</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="form.include_sections.glucose_trends" class="rounded text-purple-600" />Glucose Trends</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="form.include_sections.meals_summary" class="rounded text-purple-600" />Meals Summary</label>
                                <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="form.include_sections.medications" class="rounded text-purple-600" />Medications</label>
                            </div>
                        </div>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-purple-600 text-white rounded text-sm">Generate Export</button>
                    </form>
                </div>

                <!-- Exports History -->
                <div class="bg-white rounded-xl shadow overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Format</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Range</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="exp in exports.data" :key="exp.id">
                                <td class="px-4 py-3">{{ new Date(exp.created_at).toLocaleDateString() }}</td>
                                <td class="px-4 py-3 uppercase font-medium">{{ exp.format }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ exp.range_start }} → {{ exp.range_end }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-xs', exp.status === 'completed' ? 'bg-green-100 text-green-700' : exp.status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700']">
                                        {{ exp.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a v-if="exp.status === 'completed'" :href="route('reports.download', exp.public_id)" class="text-purple-600 text-xs hover:underline">Download</a>
                                </td>
                            </tr>
                            <tr v-if="!exports.data.length">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">No exports yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
