<script setup>
import { Head, Link } from '@inertiajs/vue3';
defineProps({ canLogin: Boolean, canRegister: Boolean });
</script>

<template>
<Head title="Suger — Blood Glucose Management" />
<div class="min-h-screen bg-white">
  <!-- Nav -->
  <nav class="fixed top-0 w-full bg-white/90 backdrop-blur-md z-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
      <div class="flex items-center gap-2">
        <span class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">S</span>
        <span class="text-xl font-bold text-gray-900">Suger</span>
      </div>
      <div class="hidden md:flex items-center gap-8 text-sm text-gray-600">
        <a href="#features" class="hover:text-purple-600 transition">Features</a>
        <a href="#how" class="hover:text-purple-600 transition">How it works</a>
        <a href="#security" class="hover:text-purple-600 transition">Security</a>
      </div>
      <div class="flex items-center gap-3">
        <Link v-if="canLogin" :href="route('login')" class="text-sm text-gray-600 hover:text-purple-600 transition px-4 py-2">Log in</Link>
        <Link v-if="canRegister" :href="route('register')" class="text-sm bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition font-medium">Get Started Free</Link>
      </div>
    </div>
  </nav>

  <!-- Hero -->
  <section class="pt-32 pb-20 px-6">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-12 items-center">
      <div>
        <span class="inline-block px-3 py-1 bg-purple-50 text-purple-700 text-xs font-semibold rounded-full mb-6">TRUSTED DIABETES MANAGEMENT</span>
        <h1 class="text-5xl lg:text-6xl font-bold text-gray-900 leading-tight mb-6">
          Take control of your <span class="text-purple-600">blood glucose</span> with confidence
        </h1>
        <p class="text-lg text-gray-500 mb-8 max-w-lg">Track readings, log meals & medications, visualize trends, and share reports with your doctor — all in one secure platform.</p>
        <div class="flex flex-wrap gap-4">
          <Link :href="route('register')" class="px-8 py-3.5 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition font-semibold text-base shadow-lg shadow-purple-200">Start Tracking — It's Free</Link>
          <a href="#features" class="px-8 py-3.5 border-2 border-gray-200 text-gray-700 rounded-xl hover:border-purple-300 hover:text-purple-600 transition font-medium text-base">Learn More</a>
        </div>
        <div class="flex items-center gap-6 mt-10 text-sm text-gray-400">
          <span class="flex items-center gap-1"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>ADA-aligned targets</span>
          <span class="flex items-center gap-1"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>GDPR-compliant</span>
          <span class="flex items-center gap-1"><svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>Encrypted data</span>
        </div>
      </div>
      <div class="relative">
        <div class="absolute -inset-4 bg-gradient-to-tr from-purple-100 to-violet-50 rounded-3xl -z-10"></div>
        <img src="/assets/images/hero-dashboard.png" alt="Suger Dashboard" class="rounded-2xl shadow-2xl w-full" />
      </div>
    </div>
  </section>

  <!-- Stats -->
  <section class="py-16 bg-gray-50">
    <div class="max-w-5xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
      <div><div class="text-3xl font-bold text-purple-600">15s</div><div class="text-sm text-gray-500 mt-1">To log a reading</div></div>
      <div><div class="text-3xl font-bold text-purple-600">256-bit</div><div class="text-sm text-gray-500 mt-1">AES encryption</div></div>
      <div><div class="text-3xl font-bold text-purple-600">PDF/CSV</div><div class="text-sm text-gray-500 mt-1">Export reports</div></div>
      <div><div class="text-3xl font-bold text-purple-600">100%</div><div class="text-sm text-gray-500 mt-1">Free to use</div></div>
    </div>
  </section>

  <!-- Features -->
  <section id="features" class="py-24 px-6">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-16">
        <h2 class="text-4xl font-bold text-gray-900 mb-4">Everything you need to manage diabetes</h2>
        <p class="text-gray-500 max-w-2xl mx-auto">Built by people who understand diabetes care. Every feature is designed around clinical best practices.</p>
      </div>
      <div class="grid md:grid-cols-3 gap-8">
        <div class="bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-lg hover:border-purple-100 transition group">
          <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center text-purple-600 text-xl mb-5 group-hover:bg-purple-600 group-hover:text-white transition">📊</div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Glucose Tracking</h3>
          <p class="text-sm text-gray-500">Log readings with full context — fasting status, time of day, symptoms, medications. Auto-converts between mg/dL and mmol/L.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-lg hover:border-purple-100 transition group">
          <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center text-green-600 text-xl mb-5 group-hover:bg-green-600 group-hover:text-white transition">🍽️</div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Meal & Nutrition</h3>
          <p class="text-sm text-gray-500">Track meals with individual food items, carbs, and calories. See how your diet affects your glucose patterns over time.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-lg hover:border-purple-100 transition group">
          <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-blue-600 text-xl mb-5 group-hover:bg-blue-600 group-hover:text-white transition">💊</div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Medication Logs</h3>
          <p class="text-sm text-gray-500">Keep track of your medications, insulin doses, and schedules. Record each dose to understand your treatment effectiveness.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-lg hover:border-purple-100 transition group">
          <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-orange-600 text-xl mb-5 group-hover:bg-orange-600 group-hover:text-white transition">📈</div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Trend Charts</h3>
          <p class="text-sm text-gray-500">Interactive charts showing daily, weekly, and monthly trends. Filter by fasting, time of day, and meal type for deeper insights.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-lg hover:border-purple-100 transition group">
          <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center text-red-600 text-xl mb-5 group-hover:bg-red-600 group-hover:text-white transition">🏃</div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Exercise Tracking</h3>
          <p class="text-sm text-gray-500">Log physical activities with duration and intensity. Understand how exercise impacts your blood glucose levels.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl p-8 hover:shadow-lg hover:border-purple-100 transition group">
          <div class="w-12 h-12 bg-violet-100 rounded-xl flex items-center justify-center text-violet-600 text-xl mb-5 group-hover:bg-violet-600 group-hover:text-white transition">📄</div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Doctor Reports</h3>
          <p class="text-sm text-gray-500">Generate PDF and CSV reports for your healthcare provider. Share your complete glucose, meal, and medication history.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- How it works -->
  <section id="how" class="py-24 px-6 bg-gray-50">
    <div class="max-w-7xl mx-auto">
      <div class="text-center mb-16">
        <h2 class="text-4xl font-bold text-gray-900 mb-4">Simple. Secure. Smart.</h2>
        <p class="text-gray-500">Get started in minutes with Google sign-in</p>
      </div>
      <div class="grid lg:grid-cols-2 gap-16 items-center">
        <img src="/assets/images/feature-tracking.png" alt="Blood glucose tracking" class="rounded-2xl shadow-xl w-full" />
        <div class="space-y-8">
          <div class="flex gap-4">
            <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-sm shrink-0">1</div>
            <div><h3 class="font-semibold text-gray-900 mb-1">Sign in with Google</h3><p class="text-sm text-gray-500">One click to create your account. Complete a quick health profile.</p></div>
          </div>
          <div class="flex gap-4">
            <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-sm shrink-0">2</div>
            <div><h3 class="font-semibold text-gray-900 mb-1">Log your readings</h3><p class="text-sm text-gray-500">Add glucose readings in under 15 seconds with smart defaults and quick tags.</p></div>
          </div>
          <div class="flex gap-4">
            <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-sm shrink-0">3</div>
            <div><h3 class="font-semibold text-gray-900 mb-1">Track meals & meds</h3><p class="text-sm text-gray-500">Log what you eat and your medication doses to see the full picture.</p></div>
          </div>
          <div class="flex gap-4">
            <div class="w-10 h-10 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-sm shrink-0">4</div>
            <div><h3 class="font-semibold text-gray-900 mb-1">Share with your doctor</h3><p class="text-sm text-gray-500">Export a comprehensive PDF report for your next appointment.</p></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Security -->
  <section id="security" class="py-24 px-6">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-2 gap-16 items-center">
      <div>
        <span class="inline-block px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full mb-4">PRIVACY FIRST</span>
        <h2 class="text-4xl font-bold text-gray-900 mb-6">Your health data is sacred</h2>
        <p class="text-gray-500 mb-8">We treat your glucose readings, medical history, and personal information with the highest level of security.</p>
        <div class="space-y-4">
          <div class="flex items-start gap-3"><div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></div><div><span class="font-medium text-gray-900">AES-256 encryption</span><p class="text-sm text-gray-500">All sensitive fields are encrypted at the application level</p></div></div>
          <div class="flex items-start gap-3"><div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></div><div><span class="font-medium text-gray-900">GDPR & HIPAA aligned</span><p class="text-sm text-gray-500">Explicit consent tracking, audit logs, and data minimization</p></div></div>
          <div class="flex items-start gap-3"><div class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center shrink-0 mt-0.5"><svg class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg></div><div><span class="font-medium text-gray-900">Full audit trail</span><p class="text-sm text-gray-500">Every data access is logged for your protection</p></div></div>
        </div>
      </div>
      <img src="/assets/images/feature-reports.png" alt="Doctor reviewing health report" class="rounded-2xl shadow-xl w-full" />
    </div>
  </section>

  <!-- CTA -->
  <section class="py-24 px-6 bg-gradient-to-br from-purple-600 to-violet-700">
    <div class="max-w-3xl mx-auto text-center">
      <h2 class="text-4xl font-bold text-white mb-6">Start managing your diabetes today</h2>
      <p class="text-purple-100 text-lg mb-10">Join Suger and take the first step toward better glucose control. Free, secure, and built for you.</p>
      <Link :href="route('register')" class="inline-block px-10 py-4 bg-white text-purple-700 rounded-xl font-bold text-lg hover:bg-purple-50 transition shadow-xl">Create Your Free Account</Link>
    </div>
  </section>

  <!-- Footer -->
  <footer class="py-12 px-6 bg-gray-900 text-gray-400">
    <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
      <div class="flex items-center gap-2">
        <span class="w-7 h-7 bg-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-xs">S</span>
        <span class="text-white font-semibold">Suger</span>
      </div>
      <div class="flex gap-6 text-sm">
        <a href="#" class="hover:text-white transition">Privacy</a>
        <a href="#" class="hover:text-white transition">Terms</a>
        <a href="#" class="hover:text-white transition">Contact</a>
      </div>
      <p class="text-xs">&copy; 2026 Suger. All rights reserved.</p>
    </div>
  </footer>
</div>
</template>
