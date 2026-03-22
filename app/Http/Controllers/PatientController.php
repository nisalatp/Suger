<?php

namespace App\Http\Controllers;

use App\Models\DoctorPatientLink;
use App\Models\DoctorPermission;
use App\Models\User;
use App\Services\UserProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class PatientController extends Controller
{
    /**
     * My Patients — list all patients linked to this doctor.
     */
    public function index()
    {
        $doctor = auth()->user();

        if (! $doctor->is_doctor) {
            abort(403, 'Doctor access required.');
        }

        $patients = UserProfileService::getLinkedPatients($doctor);

        // Build a summary for each patient
        $patientSummaries = $patients->map(function (User $patient) {
            $perm = $patient->permission;
            return [
                'id'         => $patient->id,
                'public_id'  => $patient->public_id,
                'name'       => $patient->name,
                'avatar_url' => $patient->avatar_url,
                'email'      => $patient->email,
                'permission' => $perm ? [
                    'active'      => $perm->active,
                    'modules'     => $perm->modules_json,
                    'format'      => $perm->format,
                    'period_type' => $perm->period_type,
                    'period_from' => $perm->period_from?->format('Y-m-d'),
                    'period_to'   => $perm->period_to?->format('Y-m-d'),
                ] : null,
                // Quick stats
                'last_glucose' => $patient->glucoseReadings()
                    ->orderByDesc('measured_at_utc')
                    ->value('value_mgdl'),
            ];
        });

        return Inertia::render('Patients/Index', [
            'patients' => $patientSummaries,
        ]);
    }

    /**
     * Show a single patient's allowed health data.
     */
    public function show(User $patient)
    {
        $doctor = auth()->user();

        if (! $doctor->is_doctor) {
            abort(403);
        }

        // Verify this doctor is linked to the patient
        DoctorPatientLink::where('doctor_user_id', $doctor->id)
            ->where('patient_user_id', $patient->id)
            ->firstOrFail();

        // Fetch the sharing permission set by the patient for this doctor
        $permission = DoctorPermission::where('patient_user_id', $patient->id)
            ->where('doctor_user_id', $doctor->id)
            ->first();

        if (! $permission || ! $permission->active) {
            return Inertia::render('Patients/Show', [
                'patient'    => ['name' => $patient->name, 'avatar_url' => $patient->avatar_url],
                'access'     => false,
                'permission' => null,
                'data'       => [],
            ]);
        }

        $modules = $permission->modules_json ?? [];
        [$from, $to] = UserProfileService::periodToDates($permission);
        $isSummary = $permission->format === 'summary';

        $data = [];

        // Glucose data
        if (! empty($modules['glucose'])) {
            $query = $patient->glucoseReadings()->orderBy('measured_at_utc');
            if ($from) $query->where('measured_at_utc', '>=', $from->utc());
            if ($to)   $query->where('measured_at_utc', '<=', $to->utc());

            $glucoseRaw = $query->get(['id', 'measured_at_utc', 'value_mgdl', 'time_of_day', 'is_fasting']);

            if ($isSummary) {
                // Group by day, avg per day
                $data['glucose'] = $glucoseRaw
                    ->groupBy(fn($r) => Carbon::parse($r->measured_at_utc)->format('Y-m-d'))
                    ->map(fn($g, $date) => [
                        'date' => $date,
                        'avg'  => round($g->avg('value_mgdl'), 1),
                        'count'=> $g->count(),
                        'min'  => $g->min('value_mgdl'),
                        'max'  => $g->max('value_mgdl'),
                    ])->values();
            } else {
                $data['glucose'] = $glucoseRaw->map(fn($r) => [
                    'date'      => Carbon::parse($r->measured_at_utc)->format('Y-m-d H:i'),
                    'value'     => $r->value_mgdl,
                    'context'   => $r->time_of_day,
                    'is_fasting'=> $r->is_fasting,
                ])->values();
            }
        }

        // Meals data
        if (! empty($modules['meals'])) {
            $query = $patient->meals()->orderBy('eaten_at_utc');
            if ($from) $query->where('eaten_at_utc', '>=', $from->utc());
            if ($to)   $query->where('eaten_at_utc', '<=', $to->utc());

            $mealsRaw = $query->get(['id', 'eaten_at_utc', 'meal_type', 'total_calories_kcal', 'total_carbs_g']);

            if ($isSummary) {
                $data['meals'] = $mealsRaw
                    ->groupBy(fn($m) => Carbon::parse($m->eaten_at_utc)->format('Y-m-d'))
                    ->map(fn($g, $date) => [
                        'date'     => $date,
                        'calories' => round($g->sum('total_calories_kcal')),
                        'carbs'    => round($g->sum('total_carbs_g'), 1),
                        'count'    => $g->count(),
                    ])->values();
            } else {
                $data['meals'] = $mealsRaw->map(fn($m) => [
                    'date'     => Carbon::parse($m->eaten_at_utc)->format('Y-m-d H:i'),
                    'type'     => $m->meal_type,
                    'calories' => $m->total_calories_kcal,
                    'carbs'    => $m->total_carbs_g,
                ])->values();
            }
        }

        // Medications data
        if (! empty($modules['medications'])) {
            $query = $patient->medicationEvents()
                ->with('medication:id,name,dose_unit')
                ->orderBy('taken_at_utc');
            if ($from) $query->where('taken_at_utc', '>=', $from->utc());
            if ($to)   $query->where('taken_at_utc', '<=', $to->utc());

            $medsRaw = $query->get();

            if ($isSummary) {
                $data['medications'] = $medsRaw
                    ->groupBy(fn($e) => Carbon::parse($e->taken_at_utc)->format('Y-m-d'))
                    ->map(fn($g, $date) => [
                        'date'  => $date,
                        'doses' => $g->count(),
                    ])->values();
            } else {
                $data['medications'] = $medsRaw->map(fn($e) => [
                    'date'       => Carbon::parse($e->taken_at_utc)->format('Y-m-d H:i'),
                    'medication' => $e->medication?->name,
                    'dose'       => $e->dose_taken_value . ' ' . ($e->medication?->dose_unit ?? ''),
                ])->values();
            }
        }

        // Exercise data
        if (! empty($modules['exercise'])) {
            $query = $patient->exercises()->orderBy('performed_at_utc');
            if ($from) $query->where('performed_at_utc', '>=', $from->utc());
            if ($to)   $query->where('performed_at_utc', '<=', $to->utc());

            $exRaw = $query->get(['id', 'performed_at_utc', 'activity_type', 'duration_minutes', 'calories_burned']);

            if ($isSummary) {
                $data['exercise'] = $exRaw
                    ->groupBy(fn($e) => Carbon::parse($e->performed_at_utc)->format('Y-m-d'))
                    ->map(fn($g, $date) => [
                        'date'     => $date,
                        'sessions' => $g->count(),
                        'duration' => $g->sum('duration_minutes'),
                        'calories' => $g->sum('calories_burned'),
                    ])->values();
            } else {
                $data['exercise'] = $exRaw->map(fn($e) => [
                    'date'     => Carbon::parse($e->performed_at_utc)->format('Y-m-d H:i'),
                    'activity' => $e->activity_type,
                    'minutes'  => $e->duration_minutes,
                    'calories' => $e->calories_burned,
                ])->values();
            }
        }

        return Inertia::render('Patients/Show', [
            'patient' => [
                'id'         => $patient->id,
                'public_id'  => $patient->public_id,
                'name'       => $patient->name,
                'avatar_url' => $patient->avatar_url,
            ],
            'access'     => true,
            'permission' => [
                'modules'     => $modules,
                'format'      => $permission->format,
                'period_type' => $permission->period_type,
                'period_from' => $permission->period_from?->format('Y-m-d'),
                'period_to'   => $permission->period_to?->format('Y-m-d'),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Multi-patient comparison — returns one module's data for N patients.
     */
    public function compare(Request $request)
    {
        $doctor = auth()->user();

        if (! $doctor->is_doctor) {
            abort(403);
        }

        $validated = $request->validate([
            'patient_ids' => 'required|array|min:1|max:6',
            'patient_ids.*' => 'integer',
            'module' => 'required|in:glucose,meals,exercise,medications',
        ]);

        $results = [];

        foreach ($validated['patient_ids'] as $patientId) {
            $link = DoctorPatientLink::where('doctor_user_id', $doctor->id)
                ->where('patient_user_id', $patientId)
                ->with('patientUser')
                ->first();

            if (! $link) continue;

            $permission = DoctorPermission::where('patient_user_id', $patientId)
                ->where('doctor_user_id', $doctor->id)
                ->first();
            $patient = $link->patientUser;
            $module = $validated['module'];

            // Check this module is allowed
            if (! $permission?->active || empty($permission->modules_json[$module])) {
                continue;
            }

            [$from, $to] = UserProfileService::periodToDates($permission);

            $series = ['patient_id' => $patientId, 'name' => $patient->name, 'points' => []];

            if ($module === 'glucose') {
                $q = $patient->glucoseReadings()->orderBy('measured_at_utc');
                if ($from) $q->where('measured_at_utc', '>=', $from->utc());
                if ($to)   $q->where('measured_at_utc', '<=', $to->utc());
                $series['points'] = $q->get(['measured_at_utc', 'value_mgdl'])
                    ->map(fn($r) => ['x' => Carbon::parse($r->measured_at_utc)->format('Y-m-d'), 'y' => $r->value_mgdl])
                    ->values();
            } elseif ($module === 'meals') {
                $q = $patient->meals()->orderBy('eaten_at_utc');
                if ($from) $q->where('eaten_at_utc', '>=', $from->utc());
                if ($to)   $q->where('eaten_at_utc', '<=', $to->utc());
                $series['points'] = $q->get(['eaten_at_utc', 'total_calories_kcal'])
                    ->map(fn($m) => ['x' => Carbon::parse($m->eaten_at_utc)->format('Y-m-d'), 'y' => $m->total_calories_kcal])
                    ->values();
            } elseif ($module === 'exercise') {
                $q = $patient->exercises()->orderBy('performed_at_utc');
                if ($from) $q->where('performed_at_utc', '>=', $from->utc());
                if ($to)   $q->where('performed_at_utc', '<=', $to->utc());
                $series['points'] = $q->get(['performed_at_utc', 'calories_burned', 'duration_minutes'])
                    ->map(fn($e) => ['x' => Carbon::parse($e->performed_at_utc)->format('Y-m-d'), 'y' => $e->calories_burned ?? $e->duration_minutes])
                    ->values();
            }

            $results[] = $series;
        }

        return response()->json(['series' => $results]);
    }

    /**
     * Enable doctor mode for the logged-in user.
     */
    public function enableDoctorMode(Request $request)
    {
        UserProfileService::enableDoctorMode(auth()->user());
        return back()->with('success', 'Doctor mode enabled. Patients who listed your email can now be seen in My Patients.');
    }

    /**
     * Disable doctor mode for the logged-in user.
     */
    public function disableDoctorMode(Request $request)
    {
        UserProfileService::disableDoctorMode(auth()->user());
        return back()->with('success', 'Doctor mode disabled.');
    }
}
