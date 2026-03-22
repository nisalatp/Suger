<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApiGlucoseController;
use App\Http\Controllers\Api\ApiMealController;
use App\Http\Controllers\Api\ApiMedicationController;
use App\Http\Controllers\Api\ApiExerciseController;
use App\Http\Controllers\Api\ApiDashboardController;
use App\Http\Controllers\Api\ApiProfileController;

// ─── Public: Authentication ───────────────────────────────────────────────────
Route::post('/auth/google', [AuthController::class, 'googleLogin']);

// ─── Protected: All routes require a valid Sanctum token ─────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);

    // Profile
    Route::get('/profile',   [ApiProfileController::class, 'show']);
    Route::patch('/profile', [ApiProfileController::class, 'update']);

    // Dashboard summary
    Route::get('/dashboard', [ApiDashboardController::class, 'index']);

    // Blood Glucose
    Route::get('/glucose',        [ApiGlucoseController::class, 'index']);
    Route::post('/glucose',       [ApiGlucoseController::class, 'store']);
    Route::get('/glucose/{id}',   [ApiGlucoseController::class, 'show']);
    Route::patch('/glucose/{id}', [ApiGlucoseController::class, 'update']);
    Route::delete('/glucose/{id}',[ApiGlucoseController::class, 'destroy']);

    // Meals
    Route::get('/meals',        [ApiMealController::class, 'index']);
    Route::post('/meals',       [ApiMealController::class, 'store']);
    Route::get('/meals/{id}',   [ApiMealController::class, 'show']);
    Route::patch('/meals/{id}', [ApiMealController::class, 'update']);
    Route::delete('/meals/{id}',[ApiMealController::class, 'destroy']);

    // Medications
    Route::get('/medications',             [ApiMedicationController::class, 'index']);
    Route::post('/medications',            [ApiMedicationController::class, 'store']);
    Route::patch('/medications/{id}',      [ApiMedicationController::class, 'update']);
    Route::delete('/medications/{id}',     [ApiMedicationController::class, 'destroy']);
    Route::post('/medications/{id}/dose',  [ApiMedicationController::class, 'recordDose']);

    // Exercise
    Route::get('/exercise',        [ApiExerciseController::class, 'index']);
    Route::post('/exercise',       [ApiExerciseController::class, 'store']);
    Route::get('/exercise/{id}',   [ApiExerciseController::class, 'show']);
    Route::patch('/exercise/{id}', [ApiExerciseController::class, 'update']);
    Route::delete('/exercise/{id}',[ApiExerciseController::class, 'destroy']);
});
