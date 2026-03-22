<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorPatientLink extends Model
{
    protected $fillable = [
        'doctor_user_id',
        'patient_user_id',
        'linked_doctor_id',
    ];

    public function doctorUser()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function patientUser()
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }

    public function linkedDoctor()
    {
        return $this->belongsTo(Doctor::class, 'linked_doctor_id');
    }
}
