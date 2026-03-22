<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiProfileController extends Controller
{
    public function show(Request $request)
    {
        $user    = $request->user();
        $profile = $user->profile;

        return response()->json([
            'id'        => $user->public_id,
            'name'      => $user->name,
            'email'     => $user->email,
            'avatar'    => $user->avatar_url,
            'is_doctor' => $user->is_doctor,
            'profile'   => $profile ? [
                'date_of_birth' => $profile->date_of_birth,
                'diabetes_type' => $profile->diabetes_type,
                'glucose_unit'  => $profile->glucose_unit ?? 'mmol',
                'weight_kg'     => $profile->weight_kg,
                'height_cm'     => $profile->height_cm,
                'bmi'           => $profile->bmi,
            ] : null,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $userFields = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $profileFields = $request->validate([
            'date_of_birth' => 'sometimes|date',
            'diabetes_type' => 'sometimes|in:type1,type2,gestational,prediabetes,unknown',
            'glucose_unit'  => 'sometimes|in:mmol,mgdl',
            'weight_kg'     => 'sometimes|nullable|numeric|min:10|max:500',
            'height_cm'     => 'sometimes|nullable|numeric|min:50|max:300',
        ]);

        if ($userFields) $user->update($userFields);

        if ($profileFields) {
            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $profileFields
            );
        }

        return $this->show($request);
    }
}
