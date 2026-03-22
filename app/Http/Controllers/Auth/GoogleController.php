<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     * Uses google_sub (not email) as the stable user key per Google's recommendation.
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Find by google_sub first, fallback to email (handles seeded users)
            $user = User::where('google_sub', $googleUser->id)->first()
                ?? User::where('email', $googleUser->email)->first();

            $attrs = [
                'google_sub' => $googleUser->id,
                'email' => $googleUser->email,
                'email_verified' => true,
                'name' => $googleUser->name,
                'given_name' => $googleUser->user['given_name'] ?? null,
                'family_name' => $googleUser->user['family_name'] ?? null,
                'avatar_url' => $googleUser->avatar,
                'locale' => $googleUser->user['locale'] ?? null,
                'last_login_at' => now(),
            ];

            if ($user) {
                $user->update($attrs);
            } else {
                $user = User::create($attrs);
            }

            // Auto-assign public_id if missing (first login)
            if (empty($user->public_id)) {
                $user->public_id = strtolower((string) Str::ulid());
                $user->save();
            }

            // Create profile record if not exists
            UserProfile::firstOrCreate(['user_id' => $user->id]);

            Auth::login($user);

            AuditService::log($user, 'auth.google_login');

            // Redirect to onboarding if profile is incomplete, otherwise dashboard
            if ($this->needsOnboarding($user)) {
                return redirect()->route('onboarding.profile');
            }

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            return redirect(route('login', absolute: false))->withErrors([
                'email' => 'Unable to authenticate with Google. Please try again.',
            ]);
        }
    }

    /**
     * Check if user needs to complete onboarding.
     * Only redirect on true first login — i.e. the profile was just created
     * and has never been saved by the user (updated_at equals created_at or is null).
     */
    private function needsOnboarding(User $user): bool
    {
        $profile = $user->profile;

        // No profile at all → first ever login
        if (!$profile) return true;

        // Profile exists but has no consents yet → still in onboarding
        if ($user->consents()->where('revoked_at', null)->count() === 0) return true;

        return false;
    }
}
