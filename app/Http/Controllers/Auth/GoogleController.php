<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->email],
                [
                    'name' => $googleUser->name,
                    'google_id' => $googleUser->id,
                    'avatar' => $googleUser->avatar,
                    // If a user registers via Google, they don't have a password.
                    // The password column is nullable in our migration.
                ]
            );

            Auth::login($user);

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Exception $e) {
            return redirect(route('login', absolute: false))->withErrors([
                'email' => 'Unable to authenticate with Google. Please try again.',
            ]);
        }
    }
}
