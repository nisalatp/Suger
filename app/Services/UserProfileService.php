<?php

namespace App\Services;

use App\Models\User;
use App\Models\DoctorPatientLink;
use App\Models\DoctorPermission;

/**
 * UserProfileService
 *
 * Centralises logic for:
 *  - Toggling doctor status for a user
 *  - Auto-linking doctors and patients when both have accounts
 *  - Resolving linked patients for a doctor user
 */
class UserProfileService
{
    /**
     * Enable doctor mode for a user.
     * Runs email-based auto-linking so the doctor sees patients who listed their email.
     */
    public static function enableDoctorMode(User $user): void
    {
        $user->update(['is_doctor' => true]);
        static::autoLinkPatients($user);
    }

    /**
     * Disable doctor mode for a user.
     */
    public static function disableDoctorMode(User $user): void
    {
        $user->update(['is_doctor' => false]);
    }

    /**
     * Auto-link: find all Doctor records across all patients that have
     * this user's email. Create DoctorPatientLink rows for each match.
     *
     * Called when a user enables doctor mode, or when any user logs in
     * for the first time (to pick up links created while they were absent).
     */
    public static function autoLinkPatients(User $doctorUser): void
    {
        if (! $doctorUser->is_doctor) {
            return;
        }

        // Find doctor records (created by patients) that match this email.
        // The email is stored encrypted — we match plaintext against decrypted.
        $doctorRecords = Doctor::with('user')
            ->withoutGlobalScopes()
            ->get()
            ->filter(function (Doctor $doc) use ($doctorUser) {
                return $doc->email_enc === $doctorUser->email;
            });

        foreach ($doctorRecords as $doctorRecord) {
            $patientUser = $doctorRecord->user;
            if (! $patientUser || $patientUser->id === $doctorUser->id) {
                continue;
            }

            DoctorPatientLink::firstOrCreate(
                [
                    'doctor_user_id'  => $doctorUser->id,
                    'patient_user_id' => $patientUser->id,
                ],
                [
                    'linked_doctor_id' => $doctorRecord->id,
                ]
            );
        }
    }

    /**
     * Get all patients linked to this doctor, with their permission configs loaded.
     *
     * @return \Illuminate\Support\Collection<User>
     */
    public static function getLinkedPatients(User $doctorUser)
    {
        return $doctorUser
            ->linkedPatients()
            ->with(['patientUser.profile'])
            ->get()
            ->map(function ($link) use ($doctorUser) {
                $patient = $link->patientUser;
                // Load permission set by the patient for this specific doctor
                $patient->permission = DoctorPermission::where('patient_user_id', $patient->id)
                    ->where('doctor_user_id', $doctorUser->id)
                    ->first();
                return $patient;
            });
    }

    /**
     * Get the permission config for a specific patient as seen by this doctor.
     */
    public static function getPermissionForPatient(User $doctorUser, User $patient): ?\App\Models\DoctorPermission
    {
        return DoctorPermission::where('patient_user_id', $patient->id)
            ->where('doctor_user_id', $doctorUser->id)
            ->first();
    }

    /**
     * Compute date range boundaries from a DoctorPermission period config.
     */
    public static function periodToDates(\App\Models\DoctorPermission $permission): array
    {
        $now = now();
        return match ($permission->period_type) {
            'last_30'   => [$now->copy()->subDays(30)->startOfDay(), $now->endOfDay()],
            'last_90'   => [$now->copy()->subDays(90)->startOfDay(), $now->endOfDay()],
            'last_year' => [$now->copy()->subYear()->startOfDay(), $now->endOfDay()],
            'custom'    => [
                $permission->period_from?->startOfDay() ?? $now->copy()->subDays(90)->startOfDay(),
                $permission->period_to?->endOfDay()     ?? $now->endOfDay(),
            ],
            default => [null, null], // 'all' — no date filter
        };
    }
}
