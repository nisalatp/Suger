<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MealItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meal_id',
        'food_name',
        'quantity',
        'quantity_unit',
        'carbs_g',
        'calories_kcal',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'carbs_g' => 'decimal:2',
            'calories_kcal' => 'decimal:2',
            'meta_json' => 'array',
        ];
    }

    public function meal()
    {
        return $this->belongsTo(Meal::class);
    }
}
