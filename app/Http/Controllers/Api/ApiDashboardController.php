<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlucoseReading;
use App\Models\Meal;
use App\Models\Medication;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApiDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user    = $request->user();
        $today   = now()->startOfDay();
        $week    = now()->subDays(7);

        // Last glucose reading
        $lastGlucose = GlucoseReading::where('user_id', $user->id)
            ->orderByDesc('recorded_at')->first();

        // 7-day glucose average
        $glucoseAvg7 = GlucoseReading::where('user_id', $user->id)
            ->where('recorded_at', '>=', $week)
            ->avg('value_mmol');

        // Today's meals
        $todayMeals = Meal::with('items')
            ->where('user_id', $user->id)
            ->whereDate('eaten_at', $today)->get();

        $todayCalories = $todayMeals->sum(fn($m) => $m->items->sum('calories'));
        $todayCarbs    = $todayMeals->sum(fn($m) => $m->items->sum('carbs_g'));

        // Today's exercise
        $todayExercise = Exercise::where('user_id', $user->id)
            ->whereDate('exercised_at', $today)->get();

        $todayMinutes  = $todayExercise->sum('duration_minutes');
        $todayBurned   = $todayExercise->sum('calories_burned');

        // Active medications
        $activeMeds = Medication::where('user_id', $user->id)
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()))
            ->count();

        // Recent glucose (last 14 readings for mini chart)
        $recentGlucose = GlucoseReading::where('user_id', $user->id)
            ->orderByDesc('recorded_at')->limit(14)->get()
            ->map(fn($r) => [
                'value' => round((float) $r->value_mmol, 2),
                'at'    => $r->recorded_at,
            ])->reverse()->values();

        return response()->json([
            'glucose' => [
                'last_value_mmol' => $lastGlucose ? round((float) $lastGlucose->value_mmol, 2) : null,
                'last_value_mgdl' => $lastGlucose ? round((float) $lastGlucose->value_mmol * 18.016, 1) : null,
                'last_at'         => $lastGlucose?->recorded_at,
                'avg_7d_mmol'     => $glucoseAvg7 ? round((float) $glucoseAvg7, 2) : null,
                'recent'          => $recentGlucose,
            ],
            'nutrition' => [
                'today_calories'  => round($todayCalories),
                'today_carbs_g'   => round($todayCarbs, 1),
                'today_meals'     => $todayMeals->count(),
            ],
            'exercise' => [
                'today_minutes'   => $todayMinutes,
                'today_burned'    => round($todayBurned),
                'today_sessions'  => $todayExercise->count(),
            ],
            'medications' => [
                'active_count'    => $activeMeds,
            ],
        ]);
    }
}
