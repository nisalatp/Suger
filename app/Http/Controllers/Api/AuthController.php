<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Exchange a Google ID token for a Sanctum personal access token.
     * Called by the iOS app after the user completes Google Sign-In.
     *
     * Request body: { "id_token": "..." }
     */
    public function googleLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        // Verify the token with Google's tokeninfo endpoint
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $request->id_token,
        ]);

        if ($response->failed() || empty($response['sub'])) {
            return response()->json(['error' => 'Invalid Google token'], 401);
        }

        $payload = $response->json();
        $googleSub = $payload['sub'];
        $email     = $payload['email'] ?? null;
        $name      = $payload['name']  ?? ($payload['given_name'] ?? 'User');
        $avatar    = $payload['picture'] ?? null;

        // Find existing user by google_sub, fall back to email
        $isNew = false;
        $user  = User::where('google_sub', $googleSub)->first()
            ?? User::where('email', $email)->first();

        if (!$user) {
            $isNew = true;
            $user  = User::create([
                'public_id'  => Str::ulid(),
                'name'       => $name,
                'email'      => $email,
                'google_sub' => $googleSub,
                'avatar_url' => $avatar,
                'password'   => bcrypt(Str::random(32)),
            ]);
        } else {
            // Sync latest Google info
            $user->update(array_filter([
                'google_sub' => $googleSub,
                'name'       => $name,
                'avatar_url' => $avatar,
            ]));
        }

        // Check if onboarding is needed
        $profile        = $user->profile;
        $needsOnboarding = $isNew || !$profile || !$profile->date_of_birth;

        // Revoke previous mobile tokens and issue a fresh one
        $user->tokens()->where('name', 'mobile')->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token'           => $token,
            'needs_onboarding'=> $needsOnboarding,
            'user'            => [
                'id'     => $user->public_id,
                'name'   => $user->name,
                'email'  => $user->email,
                'avatar' => $user->avatar_url,
            ],
        ]);
    }

    /**
     * Revoke the current token (logout).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Return the authenticated user's info.
     */
    public function me(Request $request)
    {
        $user    = $request->user();
        $profile = $user->profile;

        return response()->json([
            'id'         => $user->public_id,
            'name'       => $user->name,
            'email'      => $user->email,
            'avatar'     => $user->avatar_url,
            'is_doctor'  => $user->is_doctor,
            'profile'    => $profile ? [
                'date_of_birth'   => $profile->date_of_birth,
                'diabetes_type'   => $profile->diabetes_type,
                'glucose_unit'    => $profile->glucose_unit,
                'weight_kg'       => $profile->weight_kg,
                'height_cm'       => $profile->height_cm,
                'bmi'             => $profile->bmi,
            ] : null,
        ]);
    }
}
