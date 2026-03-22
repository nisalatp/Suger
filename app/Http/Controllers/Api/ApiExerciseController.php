<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\Request;

class ApiExerciseController extends Controller
{
    public function index(Request $request)
    {
        $q = Exercise::where('user_id', $request->user()->id)
            ->orderByDesc('exercised_at');

        if ($request->has('from')) $q->where('exercised_at', '>=', $request->from);
        if ($request->has('to'))   $q->where('exercised_at', '<=', $request->to);

        return response()->json(['data' => $q->limit(200)->get()->map(fn($e) => $this->format($e))]);
    }

    public function show(Request $request, string $id)
    {
        $e = Exercise::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();
        return response()->json($this->format($e));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'activity_type'   => 'required|string|max:100',
            'duration_minutes'=> 'required|integer|min:1',
            'calories_burned' => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string|max:500',
            'exercised_at'    => 'nullable|date',
        ]);

        $e = Exercise::create(array_merge($data, [
            'public_id'    => \Str::ulid(),
            'user_id'      => $request->user()->id,
            'exercised_at' => $data['exercised_at'] ?? now(),
        ]));

        return response()->json($this->format($e), 201);
    }

    public function update(Request $request, string $id)
    {
        $e = Exercise::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();

        $e->update($request->only(['activity_type','duration_minutes','calories_burned','notes','exercised_at']));
        return response()->json($this->format($e));
    }

    public function destroy(Request $request, string $id)
    {
        Exercise::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail()->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function format(Exercise $e): array
    {
        return [
            'id'               => $e->public_id,
            'activity_type'    => $e->activity_type,
            'duration_minutes' => $e->duration_minutes,
            'calories_burned'  => $e->calories_burned,
            'notes'            => $e->notes,
            'exercised_at'     => $e->exercised_at,
            'created_at'       => $e->created_at,
        ];
    }
}
