<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasPublicId;
use App\Traits\EncryptsAttributes;

class Meal extends Model
{
    use SoftDeletes, HasPublicId, EncryptsAttributes;

    protected $fillable = [
        'user_id',
        'eaten_at_utc',
        'eaten_tz',
        'meal_type',
        'total_carbs_g',
        'total_calories_kcal',
        'notes_enc',
    ];

    protected array $encryptedAttributes = [
        'notes_enc',
    ];

    protected function casts(): array
    {
        return [
            'eaten_at_utc' => 'datetime',
            'total_carbs_g' => 'decimal:2',
            'total_calories_kcal' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(MealItem::class);
    }
}
