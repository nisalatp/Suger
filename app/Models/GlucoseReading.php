<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class GlucoseReading extends Model
{
    use SoftDeletes, HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'measured_at_utc',
        'measured_tz',
        'value_raw',
        'unit',
        'value_mgdl',
        'time_of_day',
        'meal_type',
        'last_meal_at_utc',
        'last_drink_at_utc',
        'is_fasting',
        'minutes_since_last_meal',
        'minutes_since_last_drink',
        'insulin_taken',
        'meds_taken',
        'symptoms_json',
        'notes_enc',
        'source',
    ];

    protected array $encryptedAttributes = [
        'notes_enc',
    ];

    protected function casts(): array
    {
        return [
            'measured_at_utc' => 'datetime',
            'last_meal_at_utc' => 'datetime',
            'last_drink_at_utc' => 'datetime',
            'value_raw' => 'decimal:2',
            'value_mgdl' => 'decimal:2',
            'is_fasting' => 'boolean',
            'insulin_taken' => 'boolean',
            'meds_taken' => 'boolean',
            'symptoms_json' => 'array',
        ];
    }

    /**
     * Convert mmol/L to mg/dL.
     */
    public static function mmolToMgdl(float $mmol): float
    {
        return round($mmol * 18.0182, 2);
    }

    /**
     * Convert mg/dL to mmol/L.
     */
    public static function mgdlToMmol(float $mgdl): float
    {
        return round($mgdl / 18.0182, 2);
    }

    /**
     * Auto-normalize to mg/dL on save.
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function (GlucoseReading $reading) {
            if ($reading->unit === 'mmol_L') {
                $reading->value_mgdl = self::mmolToMgdl($reading->value_raw);
            } else {
                $reading->value_mgdl = $reading->value_raw;
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
