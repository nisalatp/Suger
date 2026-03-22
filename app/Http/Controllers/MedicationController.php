<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\MedicationEvent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MedicationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $tz = $user->timezone ?? 'UTC';
        $nowUser = \Carbon\Carbon::now($tz);

        // -- Active Medications List (For Quick Log) --
        $medications = $user->medications()
            ->withCount('events')
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        // -- Alerts Logic --
        $alerts = [];
        $todayEvents = $user->medicationEvents()
            ->where('taken_at_utc', '>=', $nowUser->copy()->startOfDay()->utc())
            ->where('taken_at_utc', '<=', $nowUser->copy()->endOfDay()->utc())
            ->get();

        $currentHour = $nowUser->hour;
        $activeMeds = $medications->where('active', true);

        if ($activeMeds->isNotEmpty()) {
            if ($todayEvents->isEmpty()) {
                if ($currentHour < 12) {
                    $alerts[] = ['type' => 'amber', 'message' => 'Morning Routine', 'time' => 'Log your AM medications'];
                } elseif ($currentHour < 20) {
                    $alerts[] = ['type' => 'amber', 'message' => 'Daily Medications', 'time' => 'No doses logged yet today'];
                } else {
                    $alerts[] = ['type' => 'red', 'message' => 'Missed Meds', 'time' => 'No activity logged today'];
                }
            } else {
                // Determine if they missed evening meds (proxy: past 20:00 but no doses after 18:00)
                $eveningDoses = $todayEvents->filter(function($e) use ($tz) {
                    return \Carbon\Carbon::parse($e->taken_at_utc)->timezone($tz)->hour >= 18;
                });
                if ($currentHour >= 20 && $eveningDoses->isEmpty()) {
                    $alerts[] = ['type' => 'amber', 'message' => 'Evening Routine', 'time' => 'Did you take your PM meds?'];
                }
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

        $chartEvents = $user->medicationEvents()
            ->where('taken_at_utc', '>=', $rangeStart->utc())
            ->where('taken_at_utc', '<=', $nowUser->copy()->endOfDay()->utc())
            ->orderBy('taken_at_utc')
            ->get()
            ->groupBy(function($e) use ($tz) {
                return \Carbon\Carbon::parse($e->taken_at_utc)->setTimezone($tz)->format('Y-m-d');
            });

        $chartData = [];
        $currentDate = $rangeStart->copy();
        while($currentDate <= $nowUser) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayEvents = $chartEvents->get($dateStr, collect());
            
            $chartData[] = [
                'date' => $currentDate->format('M j'),
                'full_date' => $dateStr,
                'doses' => $dayEvents->count(),
                // Support calendar widget mapping
                'calories' => $dayEvents->count(), 
            ];
            $currentDate->addDay();
        }

        // -- Paginated History --
        $query = $user->medicationEvents()->with('medication:id,name,route')->orderByDesc('taken_at_utc');
        
        if ($request->filled('from')) {
            $query->where('taken_at_utc', '>=', \Carbon\Carbon::parse($request->from)->startOfDay()->utc());
        }
        if ($request->filled('to')) {
            $query->where('taken_at_utc', '<=', \Carbon\Carbon::parse($request->to)->endOfDay()->utc());
        }

        $paginatedEvents = $query->paginate(20)->withQueryString();

        return Inertia::render('Medications/Index', [
            'medications' => $medications,
            'events' => $paginatedEvents,
            'filters' => $request->only(['from', 'to', 'chart_range']),
            'chartData' => $chartData,
            'alerts' => $alerts,
            'stats' => [
                'today_doses' => $todayEvents->count(),
            ]
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

    /**
     * Remove a medication dose event.
     */
    public function destroyEvent(\App\Models\MedicationEvent $event)
    {
        if ($event->user_id !== auth()->id()) {
            abort(404);
        }

        $event->delete();

        AuditService::log(auth()->user(), 'medication_event.deleted', 'medication_event', $event->id);

        return back()->with('success', 'Dose record removed.');
    }
}
