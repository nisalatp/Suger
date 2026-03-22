<?php

namespace App\Http\Controllers;

use App\Models\GlucoseReading;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlucoseReadingController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = $user->glucoseReadings()->orderByDesc('measured_at_utc');

        // Filters
        if ($request->filled('from')) {
            $query->where('measured_at_utc', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('measured_at_utc', '<=', $request->to);
        }
        $mealType = $request->input('meal_type');
        $mealTiming = $request->input('meal_timing');
        $timesOfDay = [];

        if ($mealType || $mealTiming) {
            
            if ($mealType && in_array($mealType, ['bedtime', 'overnight'])) {
                $timesOfDay[] = $mealType;
            } else {
                $types = $mealType ? [$mealType] : ['breakfast', 'lunch', 'dinner'];
                $timings = $mealTiming ? [$mealTiming] : ['pre', 'post'];
                
                foreach ($types as $type) {
                    foreach ($timings as $timing) {
                        $timesOfDay[] = "{$timing}_{$type}";
                    }
                }
            }
            
            if (!empty($timesOfDay)) {
                $query->whereIn('time_of_day', $timesOfDay);
            }
        }

        if ($request->has('fasting')) {
            $query->where('is_fasting', $request->boolean('fasting'));
        }

        $readings = $query->paginate(25)->withQueryString();

        // Chart data filtering
        $chartRange = $request->input('chart_range', '1month'); // Default to month
        $subDate = match($chartRange) {
            'today' => now()->startOfDay(),
            '1week' => now()->subDays(7),
            '1month' => now()->subDays(30),
            '3month' => now()->subMonths(3),
            '1year' => now()->subYear(),
            '5year' => now()->subYears(5),
            default => now()->subDays(30),
        };

        $chartQuery = $user->glucoseReadings()
            ->where('measured_at_utc', '>=', $subDate)
            ->orderBy('measured_at_utc')
            ->select('measured_at_utc', 'value_mgdl', 'is_fasting', 'time_of_day');

        if (!empty($timesOfDay)) {
            $chartQuery->whereIn('time_of_day', $timesOfDay);
        }
        if ($request->has('fasting')) {
            $chartQuery->where('is_fasting', $request->boolean('fasting'));
        }

        $chartData = $chartQuery->get();

        // Smart missing data & upcoming reading alerts
        $alerts = [];
        $now = now();
        $hour = $now->hour;
        $todayReadings = $user->glucoseReadings()
            ->whereDate('measured_at_utc', $now->toDateString())
            ->get();

        // Basic mock schedule for alerts
        $schedule = [
            ['name' => 'Fasting', 'check' => 'is_fasting', 'target_hour' => 8, 'tolerance' => 2], // 8 AM +/- 2 hrs
            ['name' => 'Post Breakfast', 'check' => 'post_breakfast', 'target_hour' => 10, 'tolerance' => 2],
            ['name' => 'Pre Lunch', 'check' => 'pre_lunch', 'target_hour' => 13, 'tolerance' => 2],
            ['name' => 'Post Lunch', 'check' => 'post_lunch', 'target_hour' => 15, 'tolerance' => 2],
            ['name' => 'Pre Dinner', 'check' => 'pre_dinner', 'target_hour' => 19, 'tolerance' => 2],
            ['name' => 'Post Dinner', 'check' => 'post_dinner', 'target_hour' => 21, 'tolerance' => 2],
        ];

        foreach ($schedule as $slot) {
            $hasRecorded = false;
            if ($slot['check'] === 'is_fasting') {
                $hasRecorded = $todayReadings->contains('is_fasting', true);
            } else {
                $hasRecorded = $todayReadings->contains('time_of_day', $slot['check']);
            }

            if (!$hasRecorded) {
                // If the user's current local hour is close to the target hour
                // We use UTC server time here, but ideally this would use the user's timezone.
                // For MVP, we'll map directly against the server time.
                $diffHours = $hour - $slot['target_hour'];
                
                if ($diffHours >= -$slot['tolerance'] && $diffHours <= 0) {
                    // Upcoming -> Amber
                    $alerts[] = [
                        'type' => 'amber',
                        'title' => 'Upcoming Reading',
                        'message' => "Time for your {$slot['name']} reading soon.",
                    ];
                } elseif ($diffHours > 0 && $diffHours <= $slot['tolerance'] + 4) {
                    // Missed -> Red (Show for 4 hours after missing it)
                    $alerts[] = [
                        'type' => 'red',
                        'title' => 'Missed Reading',
                        'message' => "You missed your {$slot['name']} reading.",
                    ];
                }
            }
        }

        return Inertia::render('Glucose/Index', [
            'readings' => $readings,
            'chartData' => $chartData,
            'chartRange' => $chartRange,
            'alerts' => $alerts,
            'filters' => $request->only(['from', 'to', 'meal_type', 'meal_timing', 'fasting']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Glucose/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'value_raw' => 'required|numeric|min:0.1|max:800',
            'unit' => 'required|in:mg_dL,mmol_L',
            'measured_at' => 'required|date',
            'measured_tz' => 'nullable|string|max:64',
            'time_of_day' => 'required|in:pre_breakfast,post_breakfast,pre_lunch,post_lunch,pre_dinner,post_dinner,bedtime,overnight,other',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner,snack,none,unknown',
            'is_fasting' => 'required|boolean',
            'last_meal_at' => 'nullable|date',
            'last_drink_at' => 'nullable|date',
            'insulin_taken' => 'nullable|boolean',
            'meds_taken' => 'nullable|boolean',
            'symptoms' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();

        // Calculate minutes since last meal/drink
        $minutesSinceMeal = null;
        $minutesSinceDrink = null;
        $measuredAt = \Carbon\Carbon::parse($validated['measured_at']);

        if (!empty($validated['last_meal_at'])) {
            $lastMeal = \Carbon\Carbon::parse($validated['last_meal_at']);
            $minutesSinceMeal = $measuredAt->diffInMinutes($lastMeal);
        }
        if (!empty($validated['last_drink_at'])) {
            $lastDrink = \Carbon\Carbon::parse($validated['last_drink_at']);
            $minutesSinceDrink = $measuredAt->diffInMinutes($lastDrink);
        }

        $reading = $user->glucoseReadings()->create([
            'value_raw' => $validated['value_raw'],
            'unit' => $validated['unit'],
            'value_mgdl' => 0, // will be auto-computed by model boot
            'measured_at_utc' => $measuredAt->utc(),
            'measured_tz' => $validated['measured_tz'] ?? $user->timezone,
            'time_of_day' => $validated['time_of_day'],
            'meal_type' => $validated['meal_type'] ?? 'unknown',
            'is_fasting' => $validated['is_fasting'],
            'last_meal_at_utc' => isset($validated['last_meal_at']) ? \Carbon\Carbon::parse($validated['last_meal_at'])->utc() : null,
            'last_drink_at_utc' => isset($validated['last_drink_at']) ? \Carbon\Carbon::parse($validated['last_drink_at'])->utc() : null,
            'minutes_since_last_meal' => $minutesSinceMeal,
            'minutes_since_last_drink' => $minutesSinceDrink,
            'insulin_taken' => $validated['insulin_taken'] ?? false,
            'meds_taken' => $validated['meds_taken'] ?? false,
            'symptoms_json' => $validated['symptoms'] ?? null,
            'notes_enc' => $validated['notes'] ?? null,
            'source' => 'manual',
        ]);

        AuditService::log($user, 'glucose_reading.created', 'glucose_reading', $reading->id);

        return redirect()->route('glucose.index')->with('success', 'Reading added.');
    }

    public function edit(GlucoseReading $glucoseReading)
    {
        // Row-level auth
        if ($glucoseReading->user_id !== auth()->id()) {
            abort(404);
        }

        return Inertia::render('Glucose/Edit', [
            'reading' => $glucoseReading,
        ]);
    }

    public function update(Request $request, GlucoseReading $glucoseReading)
    {
        if ($glucoseReading->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'value_raw' => 'required|numeric|min:0.1|max:800',
            'unit' => 'required|in:mg_dL,mmol_L',
            'time_of_day' => 'required|in:pre_breakfast,post_breakfast,pre_lunch,post_lunch,pre_dinner,post_dinner,bedtime,overnight,other',
            'is_fasting' => 'required|boolean',
            'insulin_taken' => 'nullable|boolean',
            'meds_taken' => 'nullable|boolean',
            'symptoms' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
        ]);

        $glucoseReading->update([
            'value_raw' => $validated['value_raw'],
            'unit' => $validated['unit'],
            'time_of_day' => $validated['time_of_day'],
            'is_fasting' => $validated['is_fasting'],
            'insulin_taken' => $validated['insulin_taken'] ?? false,
            'meds_taken' => $validated['meds_taken'] ?? false,
            'symptoms_json' => $validated['symptoms'] ?? null,
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        AuditService::log(auth()->user(), 'glucose_reading.updated', 'glucose_reading', $glucoseReading->id);

        return redirect()->route('glucose.index')->with('success', 'Reading updated.');
    }

    public function destroy(GlucoseReading $glucoseReading)
    {
        if ($glucoseReading->user_id !== auth()->id()) {
            abort(404);
        }

        $glucoseReading->delete();

        AuditService::log(auth()->user(), 'glucose_reading.deleted', 'glucose_reading', $glucoseReading->id);

        return redirect()->route('glucose.index')->with('success', 'Reading deleted.');
    }
}
