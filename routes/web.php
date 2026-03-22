<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlucoseReadingController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MedicationController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Public ──
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// ── Google OAuth ──
Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleController::class, 'redirect'])->name('google.login');
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleController::class, 'callback']);

// ── Authenticated routes ──
Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Onboarding
    Route::get('/onboarding/profile', [ProfileController::class, 'showOnboarding'])->name('onboarding.profile');
    Route::post('/onboarding/profile', [ProfileController::class, 'saveOnboarding'])->name('onboarding.profile.save');
    Route::get('/onboarding/consent', [ProfileController::class, 'showConsent'])->name('onboarding.consent');
    Route::post('/onboarding/consent', [ProfileController::class, 'saveConsent'])->name('onboarding.consent.save');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Glucose Readings
    Route::resource('glucose', GlucoseReadingController::class)->parameters([
        'glucose' => 'glucoseReading:public_id',
    ]);

    // Nutrition API
    Route::post('/nutrition/analyze', [\App\Http\Controllers\NutritionController::class, 'analyze'])->name('nutrition.analyze');

    // Meals
    Route::resource('meals', MealController::class)->parameters([
        'meals' => 'meal:public_id',
    ]);

    // Medications
    Route::resource('medications', MedicationController::class)->except(['show', 'edit']);
    Route::post('/medications/{medication}/dose', [MedicationController::class, 'recordDose'])->name('medications.dose');
    Route::delete('/medication-events/{event}', [MedicationController::class, 'destroyEvent'])->name('medications.events.destroy');

    // Exercise
    Route::resource('exercise', ExerciseController::class)->parameters([
        'exercise' => 'exercise:public_id',
    ]);

    // Doctors — patient-side doctor linking & permissions
    Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');
    Route::get('/doctors/search', [DoctorController::class, 'search'])->name('doctors.search');
    Route::post('/doctors/link', [DoctorController::class, 'link'])->name('doctors.link');
    Route::delete('/doctors/{doctorUserId}', [DoctorController::class, 'unlink'])->name('doctors.unlink');
    Route::patch('/doctors/{doctorUserId}/permission', [DoctorController::class, 'updatePermission'])->name('doctors.permission');


    // Doctor Portal — My Patients
    Route::get('/my-patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/my-patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
    Route::post('/my-patients/compare', [PatientController::class, 'compare'])->name('patients.compare');

    // User profile: toggle doctor mode
    Route::post('/profile/enable-doctor', [PatientController::class, 'enableDoctorMode'])->name('profile.enable_doctor');
    Route::post('/profile/disable-doctor', [PatientController::class, 'disableDoctorMode'])->name('profile.disable_doctor');

    // Reports
    Route::get('/reports', [ReportExportController::class, 'index'])->name('reports.index');
    Route::post('/reports', [ReportExportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{reportExport:public_id}/download', [ReportExportController::class, 'download'])->name('reports.download');
});

require __DIR__.'/auth.php';
