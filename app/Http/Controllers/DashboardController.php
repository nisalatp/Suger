<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Last reading
        $lastReading = $user->glucoseReadings()
            ->orderByDesc('measured_at_utc')
            ->first();

        // 7-day average
        $sevenDayAvg = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', now()->subDays(7))
            ->avg('value_mgdl');

        // Fasting average (last 7 days)
        $fastingAvg = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', now()->subDays(7))
            ->where('is_fasting', true)
            ->avg('value_mgdl');

        // Post-meal average (last 7 days)
        $postMealAvg = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', now()->subDays(7))
            ->where('is_fasting', false)
            ->avg('value_mgdl');

        // Today's readings count
        $todayCount = $user->glucoseReadings()
            ->whereDate('measured_at_utc', today())
            ->count();

        // High/low alerts (last 24 hours)
        $alerts = [];
        $recentReadings = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', now()->subHours(24))
            ->get();

        foreach ($recentReadings as $reading) {
            if ($reading->value_mgdl > 180) {
                $alerts[] = [
                    'type' => 'high',
                    'value' => $reading->value_mgdl,
                    'time' => $reading->measured_at_utc,
                    'message' => "High reading: {$reading->value_mgdl} mg/dL",
                ];
            } elseif ($reading->value_mgdl < 70) {
                $alerts[] = [
                    'type' => 'low',
                    'value' => $reading->value_mgdl,
                    'time' => $reading->measured_at_utc,
                    'message' => "Low reading: {$reading->value_mgdl} mg/dL",
                ];
            }
        }

        // Intelligent Missing Meal/Reading Reminders
        $userTimezone = $user->profile?->timezone ?? 'UTC';
        $localNow = now()->setTimezone($userTimezone);
        $hour = $localNow->hour;

        // 1. Time-of-day Meal Reminders
        $todaysMeals = $user->meals()
            ->where('eaten_at_utc', '>=', $localNow->copy()->startOfDay()->utc())
            ->pluck('meal_type')
            ->toArray();
            
        $todaysReadings = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', $localNow->copy()->startOfDay()->utc())
            ->pluck('time_of_day')
            ->toArray();

        if ($hour >= 9 && $hour < 12 && !in_array('breakfast', $todaysMeals)) {
            if (!in_array('pre_breakfast', $todaysReadings) && !in_array('post_breakfast', $todaysReadings)) {
                $alerts[] = ['type' => 'reminder', 'message' => "Good morning! Don't forget to log your breakfast and glucose readings."];
            } else {
                $alerts[] = ['type' => 'reminder', 'message' => "You've logged morning glucose, but forgot to log your breakfast details."];
            }
        } elseif ($hour >= 14 && $hour < 18 && !in_array('lunch', $todaysMeals)) {
            $alerts[] = ['type' => 'reminder', 'message' => "Good afternoon! Don't forget to log your lunch."];
        } elseif ($hour >= 20 && $hour < 23 && !in_array('dinner', $todaysMeals)) {
            $alerts[] = ['type' => 'reminder', 'message' => "Good evening! Don't forget to log your dinner."];
        }

        // 2. Pair pre-meal readings with missing post-meal readings
        $latestReading = $recentReadings->first();
        if ($latestReading && str_starts_with($latestReading->time_of_day, 'pre_')) {
            $mealType = str_replace('pre_', '', $latestReading->time_of_day);
            $hoursSincePre = now()->diffInHours($latestReading->measured_at_utc);
            
            $hasPostReading = $user->glucoseReadings()
                ->where('time_of_day', 'post_' . $mealType)
                ->where('measured_at_utc', '>=', $latestReading->measured_at_utc)
                ->exists();

            if (!$hasPostReading && $hoursSincePre >= 2 && $hoursSincePre <= 6) {
                $alerts[] = [
                    'type' => 'reminder',
                    'message' => "It's been {$hoursSincePre} hours since your pre-{$mealType} reading. Time to check your post-{$mealType} glucose!",
                ];
            }
        }

        // Recent readings for mini chart
        $recentChartData = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', now()->subDays(7))
            ->orderBy('measured_at_utc')
            ->select('measured_at_utc', 'value_mgdl', 'is_fasting')
            ->get();

        // ---------------------------------------------------------
        // Metabolic Chart Data Aggregation
        // ---------------------------------------------------------
        $chartRange = $request->query('chart_range', '1week');
        $rangeEnd = now()->endOfDay();
        
        $rangeStart = match ($chartRange) {
            'today' => now()->startOfDay(),
            '1month' => now()->subDays(30)->startOfDay(),
            '3month' => now()->subDays(90)->startOfDay(),
            '1year' => now()->subDays(365)->startOfDay(),
            '5year' => now()->subDays(1825)->startOfDay(),
            default => now()->subDays(7)->startOfDay(), // 1week
        };

        $period = \Carbon\CarbonPeriod::create($rangeStart, '1 day', $rangeEnd);

        $mealsQuery = $user->meals()->whereBetween('eaten_at_utc', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])->get();
        $exercisesQuery = $user->exercises()->whereBetween('performed_at_utc', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])->get();
        $glucoseQuery = $user->glucoseReadings()->whereBetween('measured_at_utc', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])->get();

        $mealsGrouped = $mealsQuery->groupBy(function($meal) use ($userTimezone) {
            return \Carbon\Carbon::parse($meal->eaten_at_utc)->setTimezone($userTimezone)->format('M d');
        });
        
        $exercisesGrouped = $exercisesQuery->groupBy(function($ex) use ($userTimezone) {
            return \Carbon\Carbon::parse($ex->performed_at_utc)->setTimezone($userTimezone)->format('M d');
        });
        
        $glucoseGrouped = $glucoseQuery->groupBy(function($gl) use ($userTimezone) {
            return \Carbon\Carbon::parse($gl->measured_at_utc)->setTimezone($userTimezone)->format('M d');
        });

        $labels = [];
        $intakeData = [];
        $burnData = [];
        $netData = [];
        $glucoseData = [];

        foreach ($period as $date) {
            $dateLabel = $date->format('M d');
            $labels[] = $dateLabel;
            
            $intake = isset($mealsGrouped[$dateLabel]) ? $mealsGrouped[$dateLabel]->sum('total_calories_kcal') : 0;
            $burn = isset($exercisesGrouped[$dateLabel]) ? $exercisesGrouped[$dateLabel]->sum('calories_burned') : 0;
            $avgGlucose = isset($glucoseGrouped[$dateLabel]) ? $glucoseGrouped[$dateLabel]->avg('value_mgdl') : null;
            
            $intakeData[] = $intake;
            $burnData[] = $burn;
            $netData[] = $intake - $burn;
            $glucoseData[] = $avgGlucose ? round($avgGlucose, 1) : null;
        }

        $metabolicChart = [
            'labels' => $labels,
            'intakeData' => $intakeData,
            'burnData' => $burnData,
            'netData' => $netData,
            'glucoseData' => $glucoseData,
        ];

        return Inertia::render('Dashboard', [
            'lastReading' => $lastReading,
            'sevenDayAvg' => $sevenDayAvg ? round($sevenDayAvg, 1) : null,
            'fastingAvg' => $fastingAvg ? round($fastingAvg, 1) : null,
            'postMealAvg' => $postMealAvg ? round($postMealAvg, 1) : null,
            'todayCount' => $todayCount,
            'alerts' => $alerts,
            'recentChartData' => $recentChartData,
            'profile' => $user->profile,
            'chartRange' => $chartRange,
            'metabolicChart' => $metabolicChart,
        ]);
    }
}
