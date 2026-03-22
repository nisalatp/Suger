<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex">
        <!-- Left: Purple branded panel -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-purple-700 via-purple-600 to-violet-700 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-20 left-10 w-64 h-64 bg-white rounded-full blur-3xl"></div>
                <div class="absolute bottom-20 right-10 w-96 h-96 bg-white rounded-full blur-3xl"></div>
            </div>
            <div class="relative z-10 flex flex-col justify-center px-16 text-white">
                <Link href="/" class="flex items-center gap-3 mb-12">
                    <span class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-white font-bold text-lg backdrop-blur-sm">S</span>
                    <span class="text-2xl font-bold">Suger</span>
                </Link>
                <h2 class="text-4xl font-bold leading-tight mb-6">Take control of your blood glucose journey</h2>
                <p class="text-purple-100 text-lg mb-10">Track readings, log meals, manage medications, and share reports with your doctor — securely.</p>
                <div class="space-y-4">
                    <div class="flex items-center gap-3 text-purple-100">
                        <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center text-sm backdrop-blur-sm">📊</div>
                        <span>Smart glucose trend analysis</span>
                    </div>
                    <div class="flex items-center gap-3 text-purple-100">
                        <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center text-sm backdrop-blur-sm">🔒</div>
                        <span>AES-256 encrypted health data</span>
                    </div>
                    <div class="flex items-center gap-3 text-purple-100">
                        <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center text-sm backdrop-blur-sm">📄</div>
                        <span>Export reports for your doctor</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Auth content -->
        <div class="flex-1 flex flex-col items-center justify-center px-6 bg-gray-50">
            <div class="w-full max-w-sm">
                <div class="lg:hidden flex items-center gap-2 justify-center mb-8">
                    <Link href="/">
                        <span class="w-9 h-9 bg-purple-600 rounded-lg flex items-center justify-center text-white font-bold">S</span>
                    </Link>
                    <span class="text-xl font-bold text-gray-900">Suger</span>
                </div>
                <slot />
            </div>
            <p class="mt-10 text-xs text-gray-400 text-center">&copy; 2026 Suger. Your health data is encrypted &amp; private.</p>
        </div>
    </div>
</template>
