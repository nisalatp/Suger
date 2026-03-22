<?php

namespace App\Http\Controllers;

use App\Models\DoctorPermission;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DoctorController extends Controller
{
    /**
     * Show the patient's linked doctors and their sharing settings.
     */
    public function index()
    {
        $patient = auth()->user();

        // Load linked doctor-users via doctor_patient_links
        $links = DB::table('doctor_patient_links')
            ->where('patient_user_id', $patient->id)
            ->get();

        $doctorUserIds = $links->pluck('doctor_user_id');
        $doctorUsers = User::whereIn('id', $doctorUserIds)->get()->keyBy('id');

        $permissions = DoctorPermission::where('patient_user_id', $patient->id)
            ->whereIn('doctor_user_id', $doctorUserIds)
            ->get()
            ->keyBy('doctor_user_id');

        $doctors = $links->map(function ($link) use ($doctorUsers, $permissions) {
            $doctorUser = $doctorUsers[$link->doctor_user_id] ?? null;
            $perm = $permissions[$link->doctor_user_id] ?? null;

            return [
                'link_id'        => $link->id,
                'doctor_user_id' => $link->doctor_user_id,
                'full_name'      => $doctorUser?->name,
                'email'          => $doctorUser?->email,
                'avatar_url'     => $doctorUser?->avatar_url,
                'permission'     => $perm ? [
                    'active'      => $perm->active,
                    'modules'     => $perm->modules_json,
                    'format'      => $perm->format,
                    'period_type' => $perm->period_type,
                    'period_from' => $perm->period_from?->format('Y-m-d'),
                    'period_to'   => $perm->period_to?->format('Y-m-d'),
                ] : null,
            ];
        })->values();

        return Inertia::render('Doctors/Index', [
            'doctors' => $doctors,
        ]);
    }

    /**
     * Search for registered doctors (users with is_doctor = true).
     * Called via GET /doctors/search?q=...
     */
    public function search(Request $request)
    {
        $q = trim($request->input('q', ''));
        $patient = auth()->user();

        // Get already-linked doctor user IDs to exclude them
        $linkedIds = DB::table('doctor_patient_links')
            ->where('patient_user_id', $patient->id)
            ->pluck('doctor_user_id');

        $results = User::where('is_doctor', true)
            ->when(!config('suger.doctor_allow_self_link', false), fn($q) => $q->where('id', '!=', $patient->id))
            ->whereNotIn('id', $linkedIds)           // Already linked
            ->where(function ($q2) use ($q) {
                if ($q !== '') {
                    $q2->where('name', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%");
                }
            })
            ->select('id', 'name', 'email', 'avatar_url')
            ->limit(10)
            ->get();

        return response()->json($results);
    }

    /**
     * Link a doctor-user to the current patient.
     * POST /doctors/link  { doctor_user_id }
     */
    public function link(Request $request)
    {
        $validated = $request->validate([
            'doctor_user_id' => 'required|exists:users,id',
        ]);

        $doctorUser = User::findOrFail($validated['doctor_user_id']);
        if (! $doctorUser->is_doctor) {
            abort(422, 'That user does not have doctor mode enabled.');
        }

        $patient = auth()->user();

        DB::table('doctor_patient_links')->updateOrInsert(
            [
                'doctor_user_id'  => $doctorUser->id,
                'patient_user_id' => $patient->id,
            ],
            ['created_at' => now(), 'updated_at' => now()]
        );

        AuditService::log($patient, 'doctor.linked', 'user', $doctorUser->id);

        return redirect()->route('doctors.index')->with('success', 'Doctor linked successfully.');
    }

    /**
     * Unlink a doctor from the current patient.
     * DELETE /doctors/unlink/{doctor_user_id}
     */
    public function unlink(int $doctorUserId)
    {
        $patient = auth()->user();

        DB::table('doctor_patient_links')
            ->where('doctor_user_id', $doctorUserId)
            ->where('patient_user_id', $patient->id)
            ->delete();

        // Also remove the sharing permission
        DoctorPermission::where('patient_user_id', $patient->id)
            ->where('doctor_user_id', $doctorUserId)
            ->delete();

        AuditService::log($patient, 'doctor.unlinked', 'user', $doctorUserId);

        return redirect()->route('doctors.index')->with('success', 'Doctor removed.');
    }

    /**
     * Upsert data-sharing permissions for a linked doctor.
     * PATCH /doctors/{doctor_user_id}/permission
     */
    public function updatePermission(Request $request, int $doctorUserId)
    {
        $patient = auth()->user();

        // Verify the link exists
        $linked = DB::table('doctor_patient_links')
            ->where('doctor_user_id', $doctorUserId)
            ->where('patient_user_id', $patient->id)
            ->exists();

        if (! $linked) {
            abort(404, 'Doctor not linked to your account.');
        }

        $validated = $request->validate([
            'active'              => 'required|boolean',
            'modules'             => 'required|array',
            'modules.glucose'     => 'boolean',
            'modules.meals'       => 'boolean',
            'modules.medications' => 'boolean',
            'modules.exercise'    => 'boolean',
            'format'              => 'required|in:records,summary',
            'period_type'         => 'required|in:all,last_30,last_90,last_year,custom',
            'period_from'         => 'nullable|date|required_if:period_type,custom',
            'period_to'           => 'nullable|date|required_if:period_type,custom|after_or_equal:period_from',
        ]);

        DoctorPermission::updateOrCreate(
            [
                'patient_user_id' => $patient->id,
                'doctor_user_id'  => $doctorUserId,
            ],
            [
                'modules_json' => $validated['modules'],
                'format'       => $validated['format'],
                'period_type'  => $validated['period_type'],
                'period_from'  => $validated['period_from'] ?? null,
                'period_to'    => $validated['period_to'] ?? null,
                'active'       => $validated['active'],
            ]
        );

        AuditService::log($patient, 'doctor.permission_updated', 'user', $doctorUserId);

        return back()->with('success', 'Sharing settings saved.');
    }
}
