<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({ meal: Object });

const form = useForm({
    eaten_at: props.meal.eaten_at_utc?.slice(0, 16) ?? '',
    meal_type: props.meal.meal_type,
    notes: props.meal.notes_enc ?? '',
    items: props.meal.items?.length
        ? props.meal.items.map(i => ({ food_name: i.food_name, quantity: i.quantity, quantity_unit: i.quantity_unit ?? 'g', carbs_g: i.carbs_g, calories_kcal: i.calories_kcal }))
        : [],
});

const naturalQuery = ref('');
const analyzing = ref(false);

const analyzeFood = async () => {
    if (!naturalQuery.value.trim()) return;
    
    analyzing.value = true;
    try {
        const response = await axios.post('/nutrition/analyze', { query: naturalQuery.value });
        if (response.data && response.data.items && response.data.items.length > 0) {
            form.items = [...form.items, ...response.data.items];
            naturalQuery.value = '';
        } else {
            alert('Could not identify any food items. Please try describing them differently or enter them manually.');
        }
    } catch (e) {
        console.error(e);
        alert('Failed to analyze food automatically. Please enter your items manually.');
    } finally {
        analyzing.value = false;
    }
};

const addItem = () => {
    form.items.push({ food_name: '', quantity: '', quantity_unit: 'g', carbs_g: '', calories_kcal: '' });
};

const removeItem = (idx) => { form.items.splice(idx, 1); };

const submit = () => {
    form.put(route('meals.update', props.meal.public_id));
};
</script>

<template>
    <Head title="Edit Meal" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('meals.index')" class="text-gray-400 hover:text-gray-600 font-medium">← Back</Link>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <h2 class="text-xl font-semibold text-gray-800">Edit Meal Log</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4 lg:px-0">
                <form @submit.prevent="submit" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-8">
                    
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Date & Time</label>
                            <input type="datetime-local" v-model="form.eaten_at" required class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white transition text-sm py-2.5 px-3" />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Meal Type</label>
                            <select v-model="form.meal_type" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white transition text-sm py-2.5 px-3 cursor-pointer">
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch">Lunch</option>
                                <option value="dinner">Dinner</option>
                                <option value="snack">Snack</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- AI Smart Logging Section -->
                    <div class="bg-purple-50 rounded-2xl p-5 border border-purple-100">
                        <label class="flex items-center gap-2 text-sm font-bold text-purple-900 mb-2">
                            <span>✨ Smart Tracking</span>
                        </label>
                        <p class="text-xs text-purple-700 mb-3 font-medium">Add more items using natural language.</p>
                        <div class="flex gap-2">
                            <input type="text" v-model="naturalQuery" @keyup.enter.prevent="analyzeFood"
                                   placeholder="e.g., '1 cup of coffee'" 
                                   class="flex-1 rounded-xl border-purple-200 shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500" />
                            <button type="button" @click="analyzeFood" :disabled="analyzing || !naturalQuery"
                                    class="px-5 py-2 bg-purple-600 text-white rounded-xl text-sm font-bold hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                                {{ analyzing ? 'Analyzing...' : 'Analyze' }}
                            </button>
                        </div>
                    </div>

                    <!-- Manual Items Editor -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <label class="text-sm font-semibold text-gray-700">Food Log</label>
                            <button type="button" @click="addItem" class="text-purple-600 text-xs font-bold px-3 py-1.5 bg-purple-50 rounded-lg hover:bg-purple-100 transition transition">+ Add Manual Item</button>
                        </div>

                        <div v-if="form.items.length === 0" class="text-center py-8 text-sm text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl mb-4">
                            Use Smart Tracking or add items manually manually.
                        </div>

                        <div v-for="(item, idx) in form.items" :key="idx" class="border border-gray-100 bg-white rounded-xl p-4 mb-3 shadow-sm relative group transition hover:border-gray-200">
                            <button type="button" @click="removeItem(idx)" class="absolute -right-2 -top-2 w-6 h-6 bg-red-100 text-red-600 rounded-full text-xs font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-sm hover:bg-red-200">✕</button>
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-5">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Food</label>
                                    <input type="text" v-model="item.food_name" required class="w-full rounded-lg border-gray-200 text-sm py-2 px-3 focus:bg-white bg-gray-50 transition" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Qty (g)</label>
                                    <input type="number" v-model="item.quantity" step="0.1" class="w-full rounded-lg border-gray-200 text-sm py-2 px-3 focus:bg-white bg-gray-50 transition text-center" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Carbs (g)</label>
                                    <input type="number" v-model="item.carbs_g" step="0.1" class="w-full rounded-lg border-purple-200 text-sm py-2 px-3 focus:bg-white bg-purple-50 text-purple-900 font-medium transition text-center" />
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Kcals</label>
                                    <input type="number" v-model="item.calories_kcal" step="0.1" class="w-full rounded-lg border-orange-200 text-sm py-2 px-3 focus:bg-white bg-orange-50 text-orange-900 font-medium transition text-center" />
                                </div>
                            </div>
                        </div>

                        <!-- Summary footer -->
                        <div v-if="form.items.length > 0" class="flex justify-end gap-6 border-t border-gray-100 pt-4 px-2">
                            <div class="text-right">
                                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Total Carbs</span>
                                <div class="text-lg font-bold text-purple-600">{{ form.items.reduce((s, i) => s + (Number(i.carbs_g)||0), 0).toFixed(1) }} g</div>
                            </div>
                            <div class="text-right">
                                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Total Kcals</span>
                                <div class="text-lg font-bold text-orange-600">{{ form.items.reduce((s, i) => s + (Number(i.calories_kcal)||0), 0).toFixed(0) }}</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white transition text-sm py-3 px-4 resize-none"></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing || form.items.length === 0"
                                class="w-full py-3.5 bg-gray-900 text-white rounded-xl hover:bg-black transition font-bold tracking-wide shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Update Meal Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
