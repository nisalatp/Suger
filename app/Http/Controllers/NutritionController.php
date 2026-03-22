<?php

namespace App\Http\Controllers;

use App\Services\NutritionService;
use Illuminate\Http\Request;

class NutritionController extends Controller
{
    /**
     * Endpoint for Vue frontend to quickly analyze nutrition.
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:1000'
        ]);

        $items = NutritionService::analyze($request->input('query'));

        // Format to what the frontend expects for meal_items
        $formatted = array_map(function ($item) {
            return [
                'food_name' => $item['name'] ?? 'Unknown',
                'quantity' => $item['serving_size_g'] ?? 100,
                'quantity_unit' => 'g',
                'calories_kcal' => $item['calories'] ?? 0,
                'carbs_g' => $item['carbohydrates_total_g'] ?? 0,
            ];
        }, $items);

        return response()->json([
            'items' => $formatted,
            'total_calories' => collect($formatted)->sum('calories_kcal'),
            'total_carbs' => collect($formatted)->sum('carbs_g'),
        ]);
    }
}
