<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DoctorController extends Controller
{
    public function index()
    {
        $doctors = auth()->user()->doctors()->orderByDesc('is_primary')->orderBy('created_at')->get();

        return Inertia::render('Doctors/Index', [
            'doctors' => $doctors,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'clinic_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();

        // If setting as primary, unset other primaries
        if ($request->boolean('is_primary')) {
            $user->doctors()->where('is_primary', true)->update(['is_primary' => false]);
        }

        $doctor = $user->doctors()->create([
            'full_name_enc' => $validated['full_name'],
            'clinic_name_enc' => $validated['clinic_name'] ?? null,
            'address_enc' => $validated['address'] ?? null,
            'phone_enc' => $validated['phone'] ?? null,
            'email_enc' => $validated['email'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        AuditService::log($user, 'doctor.created', 'doctor', $doctor->id);

        return redirect()->route('doctors.index')->with('success', 'Doctor added.');
    }

    public function update(Request $request, Doctor $doctor)
    {
        if ($doctor->user_id !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'clinic_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = auth()->user();

        if ($request->boolean('is_primary')) {
            $user->doctors()->where('id', '!=', $doctor->id)->where('is_primary', true)->update(['is_primary' => false]);
        }

        $doctor->update([
            'full_name_enc' => $validated['full_name'],
            'clinic_name_enc' => $validated['clinic_name'] ?? null,
            'address_enc' => $validated['address'] ?? null,
            'phone_enc' => $validated['phone'] ?? null,
            'email_enc' => $validated['email'] ?? null,
            'is_primary' => $request->boolean('is_primary'),
            'notes_enc' => $validated['notes'] ?? null,
        ]);

        AuditService::log($user, 'doctor.updated', 'doctor', $doctor->id);

        return redirect()->route('doctors.index')->with('success', 'Doctor updated.');
    }

    public function destroy(Doctor $doctor)
    {
        if ($doctor->user_id !== auth()->id()) {
            abort(404);
        }

        $doctor->delete();
        AuditService::log(auth()->user(), 'doctor.deleted', 'doctor', $doctor->id);

        return redirect()->route('doctors.index')->with('success', 'Doctor removed.');
    }
}
