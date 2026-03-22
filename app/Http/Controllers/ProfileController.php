<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Show the onboarding profile form.
     */
    public function showOnboarding()
    {
        $user = auth()->user();
        $profile = $user->profile ?? new UserProfile();

        return Inertia::render('Onboarding/Profile', [
            'user' => $user->only('name', 'given_name', 'family_name', 'email', 'avatar_url'),
            'profile' => $profile,
        ]);
    }

    /**
     * Save the onboarding profile.
     */
    public function saveOnboarding(Request $request)
    {
        $validated = $request->validate([
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'height_cm' => 'nullable|numeric|min:30|max:300',
            'weight_kg' => 'nullable|numeric|min:10|max:500',
            'diabetes_type' => 'required|in:type1,type2,gestational,prediabetes,other,unknown',
            'diagnosis_date' => 'nullable|date|before:today',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown',
        ]);

        $user = auth()->user();
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        AuditService::log($user, 'profile.updated', 'user_profile', $user->id);

        return redirect()->route('onboarding.consent');
    }

    /**
     * Show the consent onboarding page.
     */
    public function showConsent()
    {
        $user = auth()->user();
        $consents = $user->consents()->get();

        return Inertia::render('Onboarding/Consent', [
            'consents' => $consents,
        ]);
    }

    /**
     * Save consents from onboarding.
     */
    public function saveConsent(Request $request)
    {
        $validated = $request->validate([
            'health_processing' => 'required|boolean|accepted',
            'terms' => 'required|boolean|accepted',
            'privacy' => 'required|boolean|accepted',
        ]);

        $user = auth()->user();

        foreach (['health_processing', 'terms', 'privacy'] as $type) {
            if ($request->boolean($type)) {
                $user->consents()->updateOrCreate(
                    ['consent_type' => $type, 'revoked_at' => null],
                    [
                        'version' => '1.0',
                        'granted_at' => now(),
                        'ui_surface' => 'web',
                    ]
                );
            }
        }

        AuditService::log($user, 'consent.granted', 'consent', $user->id);

        return redirect()->route('dashboard');
    }

    /**
     * Show the profile edit page (post-onboarding).
     */
    public function edit()
    {
        $user = auth()->user();
        $profile = $user->profile;

        return Inertia::render('Profile/Edit', [
            'profile' => $profile ? [
                'date_of_birth'              => $profile->date_of_birth?->format('Y-m-d'),
                'gender'                     => $profile->gender,
                'height_cm'                  => $profile->height_cm,
                'weight_kg'                  => $profile->weight_kg,
                'bmi'                        => $profile->bmi,
                'diabetes_type'              => $profile->diabetes_type,
                'diagnosis_date'             => $profile->diagnosis_date?->format('Y-m-d'),
                'blood_group'                => $profile->blood_group,
                'family_history_summary_enc' => $profile->family_history_summary_enc,
            ] : null,
        ]);
    }

    /**
     * Update the user's profile.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'date_of_birth'              => 'nullable|date|before:today',
            'gender'                     => 'nullable|in:male,female,other',
            'height_cm'                  => 'nullable|numeric|min:30|max:300',
            'weight_kg'                  => 'nullable|numeric|min:10|max:500',
            'diabetes_type'              => 'nullable|in:type1,type2,gestational,prediabetes,other,unknown',
            'diagnosis_date'             => 'nullable|date|before:today',
            'blood_group'                => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-,unknown',
            'family_history_summary_enc' => 'nullable|string|max:5000',
        ]);

        $user = auth()->user();

        // updateOrCreate so users who skipped onboarding still get a profile row.
        // We pass all validated fields (including explicit nulls) so clearing a field
        // like date_of_birth actually writes NULL instead of being silently dropped.
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        AuditService::log($user, 'profile.updated', 'user_profile', $user->id);

        return back()->with('success', 'Profile updated.');
    }
}
