<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasPublicId;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasPublicId;

    protected $fillable = [
        'public_id',
        'google_sub',
        'email',
        'email_verified',
        'name',
        'given_name',
        'family_name',
        'avatar_url',
        'locale',
        'timezone',
        'password',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ──

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function glucoseReadings()
    {
        return $this->hasMany(GlucoseReading::class);
    }

    public function meals()
    {
        return $this->hasMany(Meal::class);
    }

    public function medications()
    {
        return $this->hasMany(Medication::class);
    }

    public function medicationEvents()
    {
        return $this->hasMany(MedicationEvent::class);
    }

    public function exercises()
    {
        return $this->hasMany(Exercise::class);
    }

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }

    public function reportExports()
    {
        return $this->hasMany(ReportExport::class);
    }

    public function consents()
    {
        return $this->hasMany(Consent::class);
    }

    public function auditEvents()
    {
        return $this->hasMany(AuditEvent::class);
    }
}
