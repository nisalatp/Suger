<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class MedicationEvent extends Model
{
    use HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'medication_id',
        'taken_at_utc',
        'dose_taken_value',
        'dose_taken_unit',
        'notes_enc',
    ];

    protected array $encryptedAttributes = [
        'notes_enc',
    ];

    protected function casts(): array
    {
        return [
            'taken_at_utc' => 'datetime',
            'dose_taken_value' => 'decimal:3',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }
}
