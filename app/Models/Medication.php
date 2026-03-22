<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'route',
        'dose_value',
        'dose_unit',
        'is_insulin',
        'schedule_json',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'dose_value' => 'decimal:3',
            'is_insulin' => 'boolean',
            'active' => 'boolean',
            'schedule_json' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(MedicationEvent::class);
    }
}
