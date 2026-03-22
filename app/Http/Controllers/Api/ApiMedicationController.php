<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medication;
use App\Models\MedicationEvent;
use Illuminate\Http\Request;

class ApiMedicationController extends Controller
{
    public function index(Request $request)
    {
        $meds = Medication::with('events')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')->get();

        return response()->json(['data' => $meds->map(fn($m) => $this->format($m))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'dose_value'  => 'required|numeric|min:0',
            'dose_unit'   => 'required|string|max:50',
            'frequency'   => 'required|string|max:100',
            'notes'       => 'nullable|string|max:500',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
        ]);

        $med = Medication::create(array_merge($data, [
            'public_id' => \Str::ulid(),
            'user_id'   => $request->user()->id,
        ]));

        return response()->json($this->format($med), 201);
    }

    public function update(Request $request, string $id)
    {
        $med = Medication::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();

        $med->update($request->only(['name','dose_value','dose_unit','frequency','notes','start_date','end_date']));
        return response()->json($this->format($med));
    }

    public function destroy(Request $request, string $id)
    {
        Medication::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail()->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function recordDose(Request $request, string $id)
    {
        $med = Medication::where('user_id', $request->user()->id)
            ->where('public_id', $id)->firstOrFail();

        $data = $request->validate([
            'taken_at' => 'nullable|date',
            'notes'    => 'nullable|string|max:255',
            'skipped'  => 'nullable|boolean',
        ]);

        $event = MedicationEvent::create([
            'public_id'     => \Str::ulid(),
            'medication_id' => $med->id,
            'user_id'       => $request->user()->id,
            'taken_at'      => $data['taken_at'] ?? now(),
            'notes'         => $data['notes'] ?? null,
            'skipped'       => $data['skipped'] ?? false,
        ]);

        return response()->json([
            'id'       => $event->public_id,
            'taken_at' => $event->taken_at,
            'skipped'  => $event->skipped,
        ], 201);
    }

    private function format(Medication $m): array
    {
        return [
            'id'         => $m->public_id,
            'name'       => $m->name,
            'dose_value' => $m->dose_value,
            'dose_unit'  => $m->dose_unit,
            'frequency'  => $m->frequency,
            'notes'      => $m->notes,
            'start_date' => $m->start_date,
            'end_date'   => $m->end_date,
            'created_at' => $m->created_at,
        ];
    }
}
