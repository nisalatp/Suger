<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExerciseController extends Controller
{
    private array $activityMets = [
        'walking' => 3.8,
        'running' => 9.8,
        'cycling' => 7.5,
        'swimming' => 8.3,
        'yoga' => 3.0,
        'weightlifting' => 4.0,
        'hiit' => 8.0,
        'sports' => 7.0,
        'other' => 5.0,
    ];

    private function calculateCalories($activityType, $intensity, $duration)
    {
        $baseMet = $this->activityMets[strtolower($activityType)] ?? 5.0;
        
        $modifier = 1.0;
        if ($intensity === 'low') $modifier = 0.8;
        if ($intensity === 'high') $modifier = 1.25;
        
        $met = $baseMet * $modifier;
        
        // Use user's profile weight if available, otherwise default to 70kg
        $weight_kg = auth()->user()->profile->weight_kg ?? 70.0;
        
        return round($met * $weight_kg * ($duration / 60));
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $tz = $user->timezone ?? 'UTC';
        $nowUser = \Carbon\Carbon::now($tz);

        // -- Table/List Data --
        $query = $user->exercises()->orderByDesc('performed_at_utc');

        if ($request->filled('from')) {
            $query->where('performed_at_utc', '>=', \Carbon\Carbon::parse($request->from)->startOfDay()->utc());
        }
        if ($request->filled('to')) {
            $query->where('performed_at_utc', '<=', \Carbon\Carbon::parse($request->to)->endOfDay()->utc());
        }

        $paginated = $query->paginate(20)->withQueryString();

        // -- Alerts Logic --
        $alerts = [];
        $todayExercises = $user->exercises()
            ->where('performed_at_utc', '>=', $nowUser->copy()->startOfDay()->utc())
            ->where('performed_at_utc', '<=', $nowUser->copy()->endOfDay()->utc())
            ->get();

        $currentHour = $nowUser->hour;

        if ($todayExercises->isEmpty()) {
            if ($currentHour < 17) {
                $alerts[] = ['type' => 'amber', 'message' => 'Daily Goal: Log Workout', 'time' => 'Move for at least 30 mins'];
            } else {
                $alerts[] = ['type' => 'red', 'message' => 'Missed Daily Activity', 'time' => 'No activity logged today'];
            }
        }

        // -- Chart Data --
        $chartRange = $request->input('chart_range', '1week');
        $rangeStart = match($chartRange) {
            'today' => $nowUser->copy()->startOfDay(),
            '1week' => $nowUser->copy()->subDays(6)->startOfDay(),
            '1month' => $nowUser->copy()->subDays(29)->startOfDay(),
            '3month' => $nowUser->copy()->subMonths(3)->startOfDay(),
            '1year'  => $nowUser->copy()->subYear()->startOfDay(),
            '5year'  => $nowUser->copy()->subYears(5)->startOfDay(),
            default  => $nowUser->copy()->subDays(6)->startOfDay(),
        };

        $chartActivities = $user->exercises()
            ->where('performed_at_utc', '>=', $rangeStart->utc())
            ->where('performed_at_utc', '<=', $nowUser->copy()->endOfDay()->utc())
            ->orderBy('performed_at_utc')
            ->get()
            ->groupBy(function($e) use ($tz) {
                return \Carbon\Carbon::parse($e->performed_at_utc)->setTimezone($tz)->format('Y-m-d');
            });

        $chartData = [];
        $currentDate = $rangeStart->copy();
        while($currentDate <= $nowUser) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayActivity = $chartActivities->get($dateStr, collect());
            
            $chartData[] = [
                'date' => $currentDate->format('M j'),
                'full_date' => $dateStr,
                'duration' => $dayActivity->sum('duration_minutes'),
                'calories' => $dayActivity->sum('calories_burned'),
            ];
            $currentDate->addDay();
        }

        // -- Today Stats --
        $todayDuration = $todayExercises->sum('duration_minutes');
        $todayCalories = $todayExercises->sum('calories_burned');

        return Inertia::render('Exercise/Index', [
            'exercises' => $paginated,
            'filters' => $request->only(['from', 'to', 'chart_range']),
            'chartData' => $chartData,
            'alerts' => $alerts,
            'stats' => [
                'today_duration' => $todayDuration,
                'today_calories' => $todayCalories,
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Exercise/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'performed_at' => 'required|date',
            'activity_type' => 'required|string|max:128',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'calories_burned' => 'nullable|integer|min:0',
            'intensity' => 'nullable|in:low,moderate,high,unknown',
            'notes' => 'nullable|string|max:2000',
        ]);

        $calories = $validated['calories_burned'] ?? $this->calculateCalories(
            $validated['activity_type'], 
            $validated['intensity'] ?? 'unknown', 
            $validated['duration_minutes']
        );

        $exercise = auth()->user()->exercises()->create([
            'performed_at_utc' => \Carbon\Carbon::parse($validated['performed_at'])->utc(),
            'activity_type' => $validated['activity_type'],
            'duration_minutes' => $validated['duration_minutes'],
            'calories_burned' => $calories,
            'intensity' => $validated['intensity'] ?? 'unknown',
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        AuditService::log(auth()->user(), 'exercise.created', 'exercise', $exercise->id);

        return redirect()->route('exercise.index')->with('success', 'Exercise logged.');
    }

    public function update(Request $request, Exercise $exercise)
    {
        if ($exercise->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'performed_at' => 'required|date',
            'activity_type' => 'required|string|max:128',
            'duration_minutes' => 'required|integer|min:1|max:1440',
            'calories_burned' => 'nullable|integer|min:0',
            'intensity' => 'nullable|in:low,moderate,high,unknown',
            'notes' => 'nullable|string|max:2000',
        ]);
        
        $calories = $validated['calories_burned'] ?? $this->calculateCalories(
            $validated['activity_type'], 
            $validated['intensity'] ?? 'unknown', 
            $validated['duration_minutes']
        );

        $exercise->update([
            'performed_at_utc' => \Carbon\Carbon::parse($validated['performed_at'])->utc(),
            'activity_type' => $validated['activity_type'],
            'duration_minutes' => $validated['duration_minutes'],
            'calories_burned' => $calories,
            'intensity' => $validated['intensity'] ?? 'unknown',
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        AuditService::log(auth()->user(), 'exercise.updated', 'exercise', $exercise->id);

        return redirect()->route('exercise.index')->with('success', 'Exercise updated.');
    }

    public function destroy(Exercise $exercise)
    {
        if ($exercise->user_id !== auth()->id()) {
            abort(404);
        }

        $exercise->delete();
        AuditService::log(auth()->user(), 'exercise.deleted', 'exercise', $exercise->id);

        return redirect()->route('exercise.index')->with('success', 'Exercise deleted.');
    }
}
