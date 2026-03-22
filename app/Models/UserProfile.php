<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\EncryptsAttributes;

class UserProfile extends Model
{
    use EncryptsAttributes;

    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'date_of_birth',
        'gender',
        'height_cm',
        'weight_kg',
        'bmi',
        'diabetes_type',
        'diagnosis_date',
        'blood_group',
        'family_history_summary_enc',
    ];

    protected array $encryptedAttributes = [
        'family_history_summary_enc',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'diagnosis_date' => 'date',
            'height_cm' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'bmi' => 'decimal:2',
        ];
    }

    /**
     * Auto-compute BMI when height and weight are present.
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function (UserProfile $profile) {
            if ($profile->height_cm && $profile->weight_kg) {
                $heightM = $profile->height_cm / 100;
                $profile->bmi = round($profile->weight_kg / ($heightM * $heightM), 2);
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
