<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlucoseReading;
use Illuminate\Http\Request;

class ApiGlucoseController extends Controller
{
    public function index(Request $request)
    {
        $q = GlucoseReading::where('user_id', $request->user()->id)
            ->orderByDesc('recorded_at');

        if ($request->has('from')) $q->where('recorded_at', '>=', $request->from);
        if ($request->has('to'))   $q->where('recorded_at', '<=', $request->to);

        $readings = $q->limit(500)->get()->map(fn($r) => $this->format($r));
        return response()->json(['data' => $readings]);
    }

    public function show(Request $request, string $id)
    {
        $r = GlucoseReading::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();
        return response()->json($this->format($r));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'value_mmol'  => 'required|numeric|min:0|max:50',
            'meal_timing' => 'nullable|in:fasting,pre_meal,post_meal,bedtime,random',
            'meal_type'   => 'nullable|in:breakfast,lunch,dinner,snack',
            'notes'       => 'nullable|string|max:500',
            'recorded_at' => 'nullable|date',
        ]);

        $r = GlucoseReading::create([
            'public_id'   => \Str::ulid(),
            'user_id'     => $request->user()->id,
            'value_mmol'  => $data['value_mmol'],
            'meal_timing' => $data['meal_timing'] ?? 'random',
            'meal_type'   => $data['meal_type'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        return response()->json($this->format($r), 201);
    }

    public function update(Request $request, string $id)
    {
        $r = GlucoseReading::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();

        $data = $request->validate([
            'value_mmol'  => 'sometimes|numeric|min:0|max:50',
            'meal_timing' => 'sometimes|in:fasting,pre_meal,post_meal,bedtime,random',
            'meal_type'   => 'sometimes|nullable|in:breakfast,lunch,dinner,snack',
            'notes'       => 'sometimes|nullable|string|max:500',
            'recorded_at' => 'sometimes|date',
        ]);

        $r->update($data);
        return response()->json($this->format($r));
    }

    public function destroy(Request $request, string $id)
    {
        GlucoseReading::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail()->delete();
        return response()->json(['message' => 'Deleted'], 200);
    }

    private function format(GlucoseReading $r): array
    {
        return [
            'id'          => $r->public_id,
            'value_mmol'  => round((float) $r->value_mmol, 2),
            'value_mgdl'  => round((float) $r->value_mmol * 18.01559, 1),
            'meal_timing' => $r->meal_timing,
            'meal_type'   => $r->meal_type,
            'notes'       => $r->notes,
            'recorded_at' => $r->recorded_at,
            'created_at'  => $r->created_at,
        ];
    }
}
