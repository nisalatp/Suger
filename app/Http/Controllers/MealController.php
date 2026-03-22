<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MealController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $tz = $user->timezone ?? 'UTC';
        $nowUser = \Carbon\Carbon::now($tz);

        // -- Table/List Data --
        $query = $user->meals()->with('items')->orderByDesc('eaten_at_utc');

        if ($request->filled('from')) {
            $query->where('eaten_at_utc', '>=', \Carbon\Carbon::parse($request->from)->startOfDay()->utc());
        }
        if ($request->filled('to')) {
            $query->where('eaten_at_utc', '<=', \Carbon\Carbon::parse($request->to)->endOfDay()->utc());
        }
        if ($request->filled('meal_type')) {
            $query->where('meal_type', $request->meal_type);
        }

        $paginated = $query->paginate(20)->withQueryString();

        // -- Alerts Logic --
        $alerts = [];
        $todayMeals = $user->meals()
            ->where('eaten_at_utc', '>=', $nowUser->copy()->startOfDay()->utc())
            ->where('eaten_at_utc', '<=', $nowUser->copy()->endOfDay()->utc())
            ->get();

        $loggedMealTypes = $todayMeals->pluck('meal_type')->toArray();
        $currentHour = $nowUser->hour;

        // Mock schedule: Breakfast (7-9), Lunch (12-14), Dinner (19-21)
        if ($currentHour >= 7 && $currentHour < 11 && !in_array('breakfast', $loggedMealTypes)) {
            $alerts[] = ['type' => 'amber', 'message' => 'Upcoming: Breakfast Log', 'time' => '7:00 AM - 9:00 AM'];
        } elseif ($currentHour >= 11 && !in_array('breakfast', $loggedMealTypes)) {
            $alerts[] = ['type' => 'red', 'message' => 'Missed: Breakfast Log', 'time' => 'Expected by 9:00 AM'];
        }

        if ($currentHour >= 12 && $currentHour < 15 && !in_array('lunch', $loggedMealTypes)) {
            $alerts[] = ['type' => 'amber', 'message' => 'Upcoming: Lunch Log', 'time' => '12:00 PM - 2:00 PM'];
        } elseif ($currentHour >= 15 && !in_array('lunch', $loggedMealTypes)) {
            $alerts[] = ['type' => 'red', 'message' => 'Missed: Lunch Log', 'time' => 'Expected by 2:00 PM'];
        }

        if ($currentHour >= 19 && $currentHour < 22 && !in_array('dinner', $loggedMealTypes)) {
            $alerts[] = ['type' => 'amber', 'message' => 'Upcoming: Dinner Log', 'time' => '7:00 PM - 9:00 PM'];
        } elseif ($currentHour >= 22 && !in_array('dinner', $loggedMealTypes)) {
            $alerts[] = ['type' => 'red', 'message' => 'Missed: Dinner Log', 'time' => 'Expected by 9:00 PM'];
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

        $chartMeals = $user->meals()
            ->where('eaten_at_utc', '>=', $rangeStart->utc())
            ->where('eaten_at_utc', '<=', $nowUser->copy()->endOfDay()->utc())
            ->orderBy('eaten_at_utc')
            ->get()
            ->groupBy(function($m) use ($tz) {
                return \Carbon\Carbon::parse($m->eaten_at_utc)->setTimezone($tz)->format('Y-m-d');
            });

        $chartData = [];
        $currentDate = $rangeStart->copy();
        while($currentDate <= $nowUser) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayMeals = $chartMeals->get($dateStr, collect());
            
            $chartData[] = [
                'date' => $currentDate->format('M j'),
                'full_date' => $dateStr,
                'calories' => $dayMeals->sum('total_calories_kcal'),
                'carbs' => $dayMeals->sum('total_carbs_g'),
            ];
            $currentDate->addDay();
        }

        // -- Today Stats --
        $todayCals = $todayMeals->sum('total_calories_kcal');
        $todayCarbs = $todayMeals->sum('total_carbs_g');

        return Inertia::render('Meals/Index', [
            'meals' => $paginated,
            'filters' => $request->only(['from', 'to', 'meal_type', 'chart_range']),
            'chartData' => $chartData,
            'alerts' => $alerts,
            'stats' => [
                'today_calories' => $todayCals,
                'today_carbs' => $todayCarbs,
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Meals/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'eaten_at' => 'required|date',
            'eaten_tz' => 'nullable|string|max:64',
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack,other',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.food_name' => 'required|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.quantity_unit' => 'nullable|string|max:32',
            'items.*.carbs_g' => 'nullable|numeric|min:0',
            'items.*.calories_kcal' => 'nullable|numeric|min:0',
        ]);

        $user = auth()->user();
        $eatenAt = \Carbon\Carbon::parse($validated['eaten_at']);

        // Calculate totals from items
        $totalCarbs = collect($validated['items'])->sum('carbs_g');
        $totalCalories = collect($validated['items'])->sum('calories_kcal');

        $meal = $user->meals()->create([
            'eaten_at_utc' => $eatenAt->utc(),
            'eaten_tz' => $validated['eaten_tz'] ?? $user->timezone,
            'meal_type' => $validated['meal_type'],
            'total_carbs_g' => $totalCarbs ?: null,
            'total_calories_kcal' => $totalCalories ?: null,
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $meal->items()->create($item);
        }

        AuditService::log($user, 'meal.created', 'meal', $meal->id);

        return redirect()->route('meals.index')->with('success', 'Meal logged.');
    }

    public function edit(Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(404);
        }

        $meal->load('items');

        return Inertia::render('Meals/Edit', [
            'meal' => $meal,
        ]);
    }

    public function update(Request $request, Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'eaten_at' => 'required|date',
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack,other',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.food_name' => 'required|string|max:255',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.quantity_unit' => 'nullable|string|max:32',
            'items.*.carbs_g' => 'nullable|numeric|min:0',
            'items.*.calories_kcal' => 'nullable|numeric|min:0',
        ]);

        $totalCarbs = collect($validated['items'])->sum('carbs_g');
        $totalCalories = collect($validated['items'])->sum('calories_kcal');

        $meal->update([
            'eaten_at_utc' => \Carbon\Carbon::parse($validated['eaten_at'])->utc(),
            'meal_type' => $validated['meal_type'],
            'total_carbs_g' => $totalCarbs ?: null,
            'total_calories_kcal' => $totalCalories ?: null,
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        // Sync items: delete old, create new
        $meal->items()->delete();
        foreach ($validated['items'] as $item) {
            $meal->items()->create($item);
        }

        AuditService::log(auth()->user(), 'meal.updated', 'meal', $meal->id);

        return redirect()->route('meals.index')->with('success', 'Meal updated.');
    }

    public function destroy(Meal $meal)
    {
        if ($meal->user_id !== auth()->id()) {
            abort(404);
        }

        $meal->delete();

        AuditService::log(auth()->user(), 'meal.deleted', 'meal', $meal->id);

        return redirect()->route('meals.index')->with('success', 'Meal deleted.');
    }
}
