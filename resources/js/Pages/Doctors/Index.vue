<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({ doctors: Array });

const showAddForm = ref(false);
const form = useForm({
    full_name: '', clinic_name: '', address: '', phone: '', email: '', is_primary: false, notes: '',
});

const submit = () => {
    form.post(route('doctors.store'), { onSuccess: () => { showAddForm.value = false; form.reset(); } });
};

const deleteDoc = (id) => { if (confirm('Remove this doctor?')) router.delete(route('doctors.destroy', id)); };
</script>

<template>
    <Head title="Doctors" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">Doctors</h2>
                <button @click="showAddForm = !showAddForm" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-medium">+ Add Doctor</button>
            </div>
        </template>
        <div class="pt-[2px] pb-6">
            <div class="w-full px-[5px]">
                <div v-if="showAddForm" class="bg-white rounded-xl shadow p-6 mb-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="text-xs text-gray-500">Full Name *</label><input type="text" v-model="form.full_name" required class="w-full rounded border-gray-300 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">Clinic</label><input type="text" v-model="form.clinic_name" class="w-full rounded border-gray-300 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">Phone</label><input type="text" v-model="form.phone" class="w-full rounded border-gray-300 text-sm" /></div>
                            <div><label class="text-xs text-gray-500">Email</label><input type="email" v-model="form.email" class="w-full rounded border-gray-300 text-sm" /></div>
                        </div>
                        <div><label class="text-xs text-gray-500">Address</label><input type="text" v-model="form.address" class="w-full rounded border-gray-300 text-sm" /></div>
                        <label class="flex items-center gap-2"><input type="checkbox" v-model="form.is_primary" class="rounded text-purple-600" /><span class="text-sm">Primary doctor</span></label>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-purple-600 text-white rounded text-sm">Save</button>
                    </form>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="doc in doctors" :key="doc.id" class="bg-white rounded-xl shadow p-5">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-semibold text-gray-800">{{ doc.full_name_enc }}</h3>
                                <p v-if="doc.clinic_name_enc" class="text-sm text-gray-500">{{ doc.clinic_name_enc }}</p>
                                <p v-if="doc.phone_enc" class="text-sm text-gray-500">📞 {{ doc.phone_enc }}</p>
                                <p v-if="doc.email_enc" class="text-sm text-gray-500">✉️ {{ doc.email_enc }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="doc.is_primary" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Primary</span>
                                <button @click="deleteDoc(doc.id)" class="text-red-400 text-xs hover:underline">Remove</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="!doctors.length" class="md:col-span-2 bg-white rounded-xl shadow p-8 text-center text-gray-400">No doctors added yet.</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
