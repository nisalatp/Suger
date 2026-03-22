<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorPermission extends Model
{
    protected $fillable = [
        'patient_user_id',
        'doctor_user_id',
        'modules_json',
        'format',
        'period_type',
        'period_from',
        'period_to',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'modules_json' => 'array',
            'active'       => 'boolean',
            'period_from'  => 'date',
            'period_to'    => 'date',
        ];
    }

    public function doctorUser()
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function patientUser()
    {
        return $this->belongsTo(User::class, 'patient_user_id');
    }
}
