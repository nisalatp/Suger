<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class Doctor extends Model
{
    use SoftDeletes, HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'full_name_enc',
        'clinic_name_enc',
        'address_enc',
        'phone_enc',
        'email_enc',
        'is_primary',
        'notes_enc',
    ];

    protected array $encryptedAttributes = [
        'full_name_enc',
        'clinic_name_enc',
        'address_enc',
        'phone_enc',
        'email_enc',
        'notes_enc',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function permission()
    {
        return $this->hasOne(DoctorPermission::class);
    }
}
