<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import axios from 'axios';

const form = useForm({
    eaten_at: new Date().toISOString().slice(0, 16),
    meal_type: 'lunch',
    notes: '',
    items: [],
});

const naturalQuery = ref('');
const analyzing = ref(false);
const breakdown = ref(null); // holds the full response from /nutrition/analyze
const fileInput = ref(null);

const triggerCamera = () => {
    if (fileInput.value) {
        fileInput.value.click();
    }
};

const handleImageUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    analyzing.value = true;
    breakdown.value = null;
    naturalQuery.value = 'Analyzing photo...';

    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post('/nutrition/analyze-vision', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        if (response.data && response.data.items && response.data.items.length > 0) {
            breakdown.value = response.data;
            const formItems = response.data.items.map(item => ({
                food_name:     item.food_name,
                quantity:      item.quantity,
                quantity_unit: item.quantity_unit,
                carbs_g:       item.carbs_g,
                calories_kcal: item.calories_kcal,
            }));
            form.items = [...form.items, ...formItems];
            naturalQuery.value = 'Photo analyzed successfully!';
            setTimeout(() => { naturalQuery.value = ''; }, 3000);
        } else {
            alert('Could not identify food in the photo. Please try again.');
            naturalQuery.value = '';
        }
    } catch (e) {
        console.error(e);
        alert('Failed to analyze photo. Ensure it is a clear image of food.');
        naturalQuery.value = '';
    } finally {
        analyzing.value = false;
        event.target.value = null; // reset input
    }
};

const analyzeFood = async () => {
    if (!naturalQuery.value.trim() || naturalQuery.value === 'Analyzing photo...' || naturalQuery.value === 'Photo analyzed successfully!') return;
    analyzing.value = true;
    breakdown.value = null;
    try {
        const response = await axios.post('/nutrition/analyze', { query: naturalQuery.value });
        if (response.data && response.data.items && response.data.items.length > 0) {
            breakdown.value = response.data;
            // Strip extended fields before appending to form.items (those are display-only)
            const formItems = response.data.items.map(item => ({
                food_name:     item.food_name,
                quantity:      item.quantity,
                quantity_unit: item.quantity_unit,
                carbs_g:       item.carbs_g,
                calories_kcal: item.calories_kcal,
            }));
            form.items = [...form.items, ...formItems];
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

const removeItem = (idx) => {
    form.items.splice(idx, 1);
};

const totalCalories = computed(() => form.items.reduce((s, i) => s + (Number(i.calories_kcal) || 0), 0));
const totalCarbs    = computed(() => form.items.reduce((s, i) => s + (Number(i.carbs_g) || 0), 0));

const submit = () => {
    form.post(route('meals.store'));
};
</script>

<template>
    <Head title="Log Meal" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('meals.index')" class="text-gray-400 hover:text-gray-600 font-medium">← Back</Link>
                <div class="w-px h-5 bg-gray-300 mx-1"></div>
                <h2 class="text-xl font-semibold text-gray-800">Log Meal</h2>
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
                    <div class="bg-gradient-to-br from-purple-50 to-violet-50 rounded-2xl p-5 border border-purple-100">
                        <label class="flex items-center gap-2 text-sm font-bold text-purple-900 mb-1">
                            <span>✨ Smart Tracking</span>
                        </label>
                        <p class="text-xs text-purple-700 mb-3 font-medium">Type what you ate naturally, and we'll calculate the macros.</p>
                        <div class="flex gap-2 items-center">
                            <!-- Hidden file input for camera/image upload -->
                            <input type="file" ref="fileInput" accept="image/*" capture="environment" class="hidden" @change="handleImageUpload" />
                            
                            <button type="button" @click="triggerCamera" :disabled="analyzing" title="Upload Photo"
                                    class="p-2 bg-white border border-purple-200 text-purple-600 rounded-xl hover:bg-purple-50 transition shadow-sm disabled:opacity-50">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                                </svg>
                            </button>
                            
                            <input type="text" v-model="naturalQuery" @keyup.enter.prevent="analyzeFood"
                                   placeholder="e.g., '3 eggs, 2 slices of toast...'" 
                                   :disabled="analyzing"
                                   class="flex-1 rounded-xl border-purple-200 shadow-sm text-sm focus:ring-purple-500 focus:border-purple-500 disabled:opacity-50 disabled:bg-gray-50" />
                            <button type="button" @click="analyzeFood" :disabled="analyzing || (!naturalQuery && !fileInput)"
                                    class="px-5 py-2 bg-purple-600 text-white rounded-xl text-sm font-bold hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">
                                {{ analyzing ? '⏳...' : '✨ Analyze' }}
                            </button>
                        </div>

                        <!-- Breakdown panel: appears after a successful analysis -->
                        <div v-if="breakdown" class="mt-4 border-t border-purple-200 pt-4">
                            <p class="text-xs font-bold text-purple-800 uppercase tracking-wider mb-3">📊 Nutrition Breakdown</p>

                            <!-- Per-item breakdown -->
                            <div class="space-y-2 mb-4">
                                <div v-for="(item, idx) in breakdown.items" :key="idx"
                                     class="bg-white/70 rounded-xl p-3 border border-purple-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-semibold text-sm text-gray-800 capitalize">{{ item.food_name }}</span>
                                        <span class="text-xs text-gray-500">{{ item.quantity }}g</span>
                                    </div>
                                    <div class="grid grid-cols-4 gap-2 text-center">
                                        <div class="bg-orange-50 rounded-lg px-2 py-1.5">
                                            <div class="text-sm font-bold text-orange-600">{{ item.calories_kcal }}</div>
                                            <div class="text-[10px] text-orange-400 font-semibold uppercase">kcal</div>
                                        </div>
                                        <div class="bg-purple-50 rounded-lg px-2 py-1.5">
                                            <div class="text-sm font-bold text-purple-600">{{ item.carbs_g }}g</div>
                                            <div class="text-[10px] text-purple-400 font-semibold uppercase">carbs</div>
                                        </div>
                                        <div class="bg-blue-50 rounded-lg px-2 py-1.5">
                                            <div class="text-sm font-bold text-blue-600">{{ item.protein_g ?? '—' }}g</div>
                                            <div class="text-[10px] text-blue-400 font-semibold uppercase">protein</div>
                                        </div>
                                        <div class="bg-yellow-50 rounded-lg px-2 py-1.5">
                                            <div class="text-sm font-bold text-yellow-600">{{ item.fat_total_g ?? '—' }}g</div>
                                            <div class="text-[10px] text-yellow-500 font-semibold uppercase">fat</div>
                                        </div>
                                    </div>
                                    <!-- Extra detail row -->
                                    <div class="flex gap-3 mt-2 text-[11px] text-gray-500">
                                        <span v-if="item.fiber_g !== undefined">🌾 Fiber: <b>{{ item.fiber_g }}g</b></span>
                                        <span v-if="item.sugar_g !== undefined">🍬 Sugar: <b>{{ item.sugar_g }}g</b></span>
                                        <span v-if="item.sodium_mg !== undefined">🧂 Sodium: <b>{{ item.sodium_mg }}mg</b></span>
                                        <span v-if="item.cholesterol_mg !== undefined">💛 Chol: <b>{{ item.cholesterol_mg }}mg</b></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Total summary bar -->
                            <div class="bg-white/80 rounded-xl p-3 border border-purple-200">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Total</p>
                                <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 text-center">
                                    <div class="col-span-1">
                                        <div class="text-base font-extrabold text-orange-600">{{ breakdown.total_calories }}</div>
                                        <div class="text-[10px] text-gray-400 font-semibold">kcal</div>
                                    </div>
                                    <div class="col-span-1">
                                        <div class="text-base font-extrabold text-purple-600">{{ breakdown.total_carbs }}g</div>
                                        <div class="text-[10px] text-gray-400 font-semibold">carbs</div>
                                    </div>
                                    <div class="col-span-1">
                                        <div class="text-base font-extrabold text-blue-600">{{ breakdown.total_protein }}g</div>
                                        <div class="text-[10px] text-gray-400 font-semibold">protein</div>
                                    </div>
                                    <div class="col-span-1">
                                        <div class="text-base font-extrabold text-yellow-600">{{ breakdown.total_fat }}g</div>
                                        <div class="text-[10px] text-gray-400 font-semibold">fat</div>
                                    </div>
                                    <div class="col-span-1">
                                        <div class="text-base font-extrabold text-green-600">{{ breakdown.total_fiber }}g</div>
                                        <div class="text-[10px] text-gray-400 font-semibold">fiber</div>
                                    </div>
                                    <div class="col-span-1">
                                        <div class="text-base font-extrabold text-pink-600">{{ breakdown.total_sugar }}g</div>
                                        <div class="text-[10px] text-gray-400 font-semibold">sugar</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Items Editor -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <label class="text-sm font-semibold text-gray-700">Food Log</label>
                            <button type="button" @click="addItem" class="text-purple-600 text-xs font-bold px-3 py-1.5 bg-purple-50 rounded-lg hover:bg-purple-100 transition transition">+ Add Manual Item</button>
                        </div>

                        <div v-if="form.items.length === 0" class="text-center py-8 text-sm text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded-xl mb-4">
                            Use Smart Tracking or add items manually to start logging foods.
                        </div>

                        <div v-for="(item, idx) in form.items" :key="idx" class="border border-gray-100 bg-white rounded-xl p-4 mb-3 shadow-sm relative group transition hover:border-gray-200">
                            <button type="button" @click="removeItem(idx)" class="absolute -right-2 -top-2 w-6 h-6 bg-red-100 text-red-600 rounded-full text-xs font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-sm hover:bg-red-200">✕</button>
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                <div class="sm:col-span-5">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Food</label>
                                    <input type="text" v-model="item.food_name" required class="w-full rounded-lg border-gray-200 text-sm py-2 px-3 focus:bg-white bg-gray-50 transition" placeholder="e.g. Scrambled Eggs" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Qty (g)</label>
                                    <input type="number" v-model="item.quantity" step="0.1" class="w-full rounded-lg border-gray-200 text-sm py-2 px-3 focus:bg-white bg-gray-50 transition text-center" placeholder="100" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Carbs (g)</label>
                                    <input type="number" v-model="item.carbs_g" step="0.1" class="w-full rounded-lg border-purple-200 text-sm py-2 px-3 focus:bg-white bg-purple-50 text-purple-900 font-medium transition text-center" placeholder="0" />
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1 block">Kcals</label>
                                    <input type="number" v-model="item.calories_kcal" step="0.1" class="w-full rounded-lg border-orange-200 text-sm py-2 px-3 focus:bg-white bg-orange-50 text-orange-900 font-medium transition text-center" placeholder="0" />
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
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:bg-white transition text-sm py-3 px-4 resize-none" placeholder="Any symptoms or context?"></textarea>
                    </div>

                    <div class="pt-4 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing || form.items.length === 0"
                                class="w-full py-3.5 bg-gray-900 text-white rounded-xl hover:bg-black transition font-bold tracking-wide shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Save Meal Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
