<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\MedicationEvent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $medications = $user->medications()
            ->withCount('events')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $recentEvents = $user->medicationEvents()
            ->with('medication:id,name')
            ->orderByDesc('taken_at_utc')
            ->take(20)
            ->get();

        return Inertia::render('Medications/Index', [
            'medications' => $medications,
            'recentEvents' => $recentEvents,
        ]);
    }

    public function create()
    {
        return Inertia::render('Medications/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'route' => 'nullable|in:oral,injection,pump,other,unknown',
            'dose_value' => 'nullable|numeric|min:0',
            'dose_unit' => 'nullable|string|max:32',
            'is_insulin' => 'nullable|boolean',
            'schedule_json' => 'nullable|array',
        ]);

        $medication = auth()->user()->medications()->create($validated);

        AuditService::log(auth()->user(), 'medication.created', 'medication', $medication->id);

        return redirect()->route('medications.index')->with('success', 'Medication added.');
    }

    public function update(Request $request, Medication $medication)
    {
        if ($medication->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'route' => 'nullable|in:oral,injection,pump,other,unknown',
            'dose_value' => 'nullable|numeric|min:0',
            'dose_unit' => 'nullable|string|max:32',
            'is_insulin' => 'nullable|boolean',
            'active' => 'nullable|boolean',
            'schedule_json' => 'nullable|array',
        ]);

        $medication->update($validated);

        AuditService::log(auth()->user(), 'medication.updated', 'medication', $medication->id);

        return redirect()->route('medications.index')->with('success', 'Medication updated.');
    }

    public function destroy(Medication $medication)
    {
        if ($medication->user_id !== auth()->id()) {
            abort(404);
        }

        $medication->delete();

        AuditService::log(auth()->user(), 'medication.deleted', 'medication', $medication->id);

        return redirect()->route('medications.index')->with('success', 'Medication removed.');
    }

    /**
     * Record a medication dose event.
     */
    public function recordDose(Request $request, Medication $medication)
    {
        if ($medication->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'taken_at' => 'required|date',
            'dose_taken_value' => 'nullable|numeric|min:0',
            'dose_taken_unit' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:2000',
        ]);

        $event = auth()->user()->medicationEvents()->create([
            'medication_id' => $medication->id,
            'taken_at_utc' => \Carbon\Carbon::parse($validated['taken_at'])->utc(),
            'dose_taken_value' => $validated['dose_taken_value'] ?? $medication->dose_value,
            'dose_taken_unit' => $validated['dose_taken_unit'] ?? $medication->dose_unit,
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        AuditService::log(auth()->user(), 'medication_event.created', 'medication_event', $event->id);

        return back()->with('success', 'Dose recorded.');
    }
}
