<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingMobileMenu = ref(false);
const isSidebarCollapsed = ref(false);

// ── Server time display ──
// Seed from server prop (Asia/Colombo), then tick every second using JS
// so the display stays live between page navigations.
const displayTime = ref(page.props.server_time ?? '');
let _timerId = null;

function _tickTime() {
    // Parse the server time once to get an offset, then advance it client-side
    const now = new Date();
    // Format: "Sun, 22 Mar · 20:36:24"
    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    // Use Asia/Colombo offset (UTC+05:30 = +330 min)
    const lk = new Date(now.getTime() + (5 * 60 + 30) * 60000 - now.getTimezoneOffset() * 60000);
    const d = lk.getUTCDay(), dt = String(lk.getUTCDate()).padStart(2,'0');
    const mo = months[lk.getUTCMonth()];
    const h = String(lk.getUTCHours()).padStart(2,'0');
    const m = String(lk.getUTCMinutes()).padStart(2,'0');
    const s = String(lk.getUTCSeconds()).padStart(2,'0');
    displayTime.value = `${days[d]}, ${dt} ${mo} · ${h}:${m}:${s}`;
}

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value ? '1' : '0');
    }
};

onMounted(() => {
    if (typeof window !== 'undefined' && window.innerWidth >= 1024) {
        const savedState = localStorage.getItem('sidebar_collapsed');
        if (savedState === '1') {
            isSidebarCollapsed.value = true;
        }
    }
    _timerId = setInterval(_tickTime, 1000);
    _tickTime(); // immediate first tick
});

onUnmounted(() => {
    if (_timerId) clearInterval(_timerId);
});

const navItems = [
    { name: 'Dashboard', route: 'dashboard', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>' },
    { name: 'Blood Glucose', route: 'glucose.index', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25c-1.745 2.508-6 8.657-6 12a6 6 0 1 0 12 0c0-3.343-4.255-9.492-6-12Z" /></svg>' },
    { name: 'Meals', route: 'meals.index', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5A2 2 0 0 0 3 14h18A2 2 0 0 0 19 12ZM7 12V9m5 3V8m5 4V9" /></svg>' },
    { name: 'Exercise', route: 'exercise.index', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h4l2-8 4 16 2-8h4" /></svg>' },
    { name: 'Medications', route: 'medications.index', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.25 14.75l-9.5-9.5a3 3 0 0 0-4.25 4.25l9.5 9.5a3 3 0 0 0 4.25-4.25Z M9 5l-4 4 M15 15l4-4" /></svg>' },
    { name: 'Doctors', route: 'doctors.index', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m-3-3h6M5 3h14A2 2 0 0121 5v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg>' },
    { name: 'Reports', route: 'reports.index', icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6zM13.5 3v7.5H21A7.5 7.5 0 0013.5 3z" /></svg>' },
];

const doctorNavItem = {
    name: 'My Patients',
    route: 'patients.index',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
};
</script>

<template>
    <div class="w-full relative overflow-x-hidden min-h-screen bg-gray-50 flex">
        
        <!-- Mobile Sidebar Backdrop -->
        <div v-if="showingMobileMenu" 
             @click="showingMobileMenu = false"
             class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden min-w-0"></div>

        <!-- Left Sidebar -->
        <nav :class="[
            'fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-100 flex flex-col transition-all duration-300 ease-in-out',
            showingMobileMenu ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
            isSidebarCollapsed ? 'lg:w-20' : 'lg:w-64'
        ]">
            <!-- Sidebar Header / Logo -->
            <div :class="['flex items-center h-16 border-b border-gray-100 shrink-0 transition-all duration-300', isSidebarCollapsed ? 'justify-center px-0' : 'px-6 gap-3']">
                <img src="/assets/images/suger-logo.png" alt="Suger" class="h-8 w-8 rounded-lg shrink-0" />
                <span v-if="!isSidebarCollapsed || showingMobileMenu" class="text-xl font-bold text-gray-900 tracking-tight whitespace-nowrap transition-opacity duration-300">Suger</span>
            </div>

            <!-- Sidebar Navigation -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden py-4 space-y-1.5 px-3">
                <Link v-for="item in navItems" :key="item.route"
                    :href="route(item.route)"
                    :title="isSidebarCollapsed ? item.name : ''"
                    v-tooltip="isSidebarCollapsed ? item.name : ''"
                    :class="[
                        'flex items-center rounded-xl text-sm font-medium transition-colors group relative',
                        isSidebarCollapsed ? 'justify-center p-3' : 'px-4 py-3 gap-3',
                        route().current(item.route) || route().current(item.route.replace('.index', '.*')) 
                            ? 'bg-purple-50 text-purple-700' 
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                    ]">
                    <span class="shrink-0 transition-transform duration-200" 
                          :class="[
                              route().current(item.route) || route().current(item.route.replace('.index', '.*')) ? 'text-purple-600' : 'text-gray-400 group-hover:text-gray-600',
                              isSidebarCollapsed ? 'scale-110' : ''
                          ]" 
                          v-html="item.icon"></span>
                    <span v-if="!isSidebarCollapsed || showingMobileMenu" class="whitespace-nowrap transition-opacity duration-300">{{ item.name }}</span>
                    
                    <!-- Tooltip for collapsed mode -->
                    <div v-if="isSidebarCollapsed && !showingMobileMenu" class="absolute left-full ml-3 hidden group-hover:block bg-gray-800 text-white text-xs font-semibold px-2 py-1.5 rounded-md whitespace-nowrap z-50">
                        {{ item.name }}
                    </div>
                </Link>
            </div>

            <!-- Doctor Portal nav item (only shown if is_doctor) -->
            <div v-if="$page.props.auth.user.is_doctor" class="px-3 pb-2">
                <div class="border-t border-gray-100 pt-2 mb-1">
                    <p v-if="!isSidebarCollapsed" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest px-1 mb-1">Doctor Portal</p>
                </div>
                <Link :href="route(doctorNavItem.route)"
                    :title="isSidebarCollapsed ? doctorNavItem.name : ''"
                    :class="[
                        'flex items-center rounded-xl text-sm font-medium transition-colors group relative',
                        isSidebarCollapsed ? 'justify-center p-3' : 'px-4 py-3 gap-3',
                        route().current(doctorNavItem.route)
                            ? 'bg-blue-50 text-blue-700'
                            : 'text-gray-600 hover:bg-blue-50/60 hover:text-blue-700'
                    ]">
                    <span class="shrink-0" v-html="doctorNavItem.icon"></span>
                    <span v-if="!isSidebarCollapsed || showingMobileMenu" class="whitespace-nowrap">{{ doctorNavItem.name }}</span>
                    <div v-if="isSidebarCollapsed && !showingMobileMenu" class="absolute left-full ml-3 hidden group-hover:block bg-gray-800 text-white text-xs font-semibold px-2 py-1.5 rounded-md whitespace-nowrap z-50">
                        {{ doctorNavItem.name }}
                    </div>
                </Link>
            </div>

            <!-- Sidebar Footer / Collapse Toggle & User Profile -->
            <div class="border-t border-gray-100 shrink-0 p-3 flex flex-col gap-2">
                <!-- Desktop Collapse Toggle -->
                <button @click="toggleSidebar" class="hidden lg:flex items-center justify-center w-full p-2.5 rounded-xl text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors">
                    <svg v-if="!isSidebarCollapsed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" /></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5" /></svg>
                </button>

                <Dropdown align="top-left" width="48">
                    <template #trigger>
                        <button :class="['flex items-center w-full p-2 rounded-xl hover:bg-gray-50 transition-colors focus:outline-none', isSidebarCollapsed ? 'justify-center' : 'gap-3 text-left']">
                            <div class="shrink-0 w-8 h-8 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-sm font-bold border border-purple-200">
                                {{ $page.props.auth.user.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <template v-if="!isSidebarCollapsed || showingMobileMenu">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-900 truncate">{{ $page.props.auth.user.name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $page.props.auth.user.email }}</p>
                                </div>
                                <svg class="h-4 w-4 text-gray-400 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                </svg>
                            </template>
                        </button>
                    </template>
                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Profile Settings</DropdownLink>
                        <form v-if="!$page.props.auth.user.is_doctor" :action="route('profile.enable_doctor')" method="POST" @submit.prevent="$inertia.post(route('profile.enable_doctor'))">
                            <button type="submit" class="w-full text-left block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 transition">Enable Doctor Mode 🩺</button>
                        </form>
                        <form v-else :action="route('profile.disable_doctor')" method="POST" @submit.prevent="$inertia.post(route('profile.disable_doctor'))">
                            <button type="submit" class="w-full text-left block px-4 py-2 text-sm leading-5 text-gray-500 hover:bg-gray-100 transition">Disable Doctor Mode</button>
                        </form>
                        <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </nav>

        <!-- Main Content Area -->
        <div :class="['flex-1 flex flex-col min-w-0 min-h-screen transition-all duration-300 ease-in-out', isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64']">
            
            <!-- Mobile Top Bar -->
            <div class="lg:hidden flex items-center justify-between h-16 px-4 bg-white border-b border-gray-100 shrink-0 sticky top-0 z-30 shadow-sm">
                <div class="flex items-center gap-2">
                    <img src="/assets/images/suger-logo.png" alt="Suger" class="h-8 w-8 rounded-lg" />
                    <span class="text-lg font-bold text-gray-900 tracking-tight">Suger</span>
                </div>
                <!-- Server time: center -->
                <div class="absolute left-1/2 -translate-x-1/2 flex flex-col items-center leading-tight pointer-events-none select-none">
                    <span class="text-[11px] font-semibold text-purple-600 tabular-nums tracking-tight">{{ displayTime }}</span>
                    <span class="text-[9px] text-gray-400 font-medium">Server · LK</span>
                </div>
                <button @click="showingMobileMenu = true" aria-label="Open menu" class="p-2 -mr-2 text-gray-500 hover:text-gray-900 rounded-lg hover:bg-gray-50 focus:outline-none">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
            </div>

            <!-- Page Heading (Balanced with Sidebar Header) -->
            <header class="bg-white border-b border-gray-100 flex items-center h-16 shrink-0 z-20" v-if="$slots.header">
                <div class="w-full px-[5px] flex items-center h-full min-w-0">
                    <div class="w-full min-w-0">
                        <slot name="header" />
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden min-w-0 w-full mb-8">
                <slot />
            </main>
        </div>
    </div>
</template>
