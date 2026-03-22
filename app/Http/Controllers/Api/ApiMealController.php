<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Models\MealItem;
use Illuminate\Http\Request;

class ApiMealController extends Controller
{
    public function index(Request $request)
    {
        $q = Meal::with('items')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('eaten_at');

        if ($request->has('from')) $q->where('eaten_at', '>=', $request->from);
        if ($request->has('to'))   $q->where('eaten_at', '<=', $request->to);

        return response()->json(['data' => $q->limit(200)->get()->map(fn($m) => $this->format($m))]);
    }

    public function show(Request $request, string $id)
    {
        $meal = Meal::with('items')->where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();
        return response()->json($this->format($meal));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'meal_type'  => 'required|in:breakfast,lunch,dinner,snack',
            'eaten_at'   => 'nullable|date',
            'notes'      => 'nullable|string|max:500',
            'items'      => 'nullable|array',
            'items.*.name'     => 'required_with:items|string|max:255',
            'items.*.calories' => 'nullable|numeric|min:0',
            'items.*.carbs_g'  => 'nullable|numeric|min:0',
            'items.*.protein_g'=> 'nullable|numeric|min:0',
            'items.*.fat_g'    => 'nullable|numeric|min:0',
            'items.*.serving_desc' => 'nullable|string|max:255',
        ]);

        $meal = Meal::create([
            'public_id' => \Str::ulid(),
            'user_id'   => $request->user()->id,
            'meal_type' => $data['meal_type'],
            'eaten_at'  => $data['eaten_at'] ?? now(),
            'notes'     => $data['notes'] ?? null,
        ]);

        foreach ($data['items'] ?? [] as $item) {
            $meal->items()->create(array_merge($item, ['public_id' => \Str::ulid()]));
        }

        return response()->json($this->format($meal->load('items')), 201);
    }

    public function update(Request $request, string $id)
    {
        $meal = Meal::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();

        $meal->update($request->only(['meal_type', 'eaten_at', 'notes']));
        return response()->json($this->format($meal->load('items')));
    }

    public function destroy(Request $request, string $id)
    {
        Meal::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail()->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function format(Meal $m): array
    {
        return [
            'id'         => $m->public_id,
            'meal_type'  => $m->meal_type,
            'eaten_at'   => $m->eaten_at,
            'notes'      => $m->notes,
            'total_calories' => $m->items->sum('calories'),
            'total_carbs'    => $m->items->sum('carbs_g'),
            'items'      => $m->items->map(fn($i) => [
                'id'           => $i->public_id,
                'name'         => $i->name,
                'calories'     => $i->calories,
                'carbs_g'      => $i->carbs_g,
                'protein_g'    => $i->protein_g,
                'fat_g'        => $i->fat_g,
                'serving_desc' => $i->serving_desc,
            ]),
        ];
    }
}
