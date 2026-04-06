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
                'food_name'        => $item['name']                    ?? 'Unknown',
                'quantity'         => $item['serving_size_g']          ?? 100,
                'quantity_unit'    => 'g',
                'calories_kcal'    => round($item['calories']                    ?? 0, 1),
                'carbs_g'          => round($item['carbohydrates_total_g']       ?? 0, 1),
                // Extended macros for the breakdown panel
                'fat_total_g'      => round($item['fat_total_g']                 ?? 0, 1),
                'fat_saturated_g'  => round($item['fat_saturated_g']            ?? 0, 1),
                'protein_g'        => round($item['protein_g']                   ?? 0, 1),
                'fiber_g'          => round($item['fiber_g']                     ?? 0, 1),
                'sugar_g'          => round($item['sugar_g']                     ?? 0, 1),
                'sodium_mg'        => round($item['sodium_mg']                   ?? 0, 0),
                'cholesterol_mg'   => round($item['cholesterol_mg']              ?? 0, 0),
            ];
        }, $items);

        return response()->json([
            'items'          => $formatted,
            'total_calories' => round(collect($formatted)->sum('calories_kcal'), 1),
            'total_carbs'    => round(collect($formatted)->sum('carbs_g'), 1),
            'total_protein'  => round(collect($formatted)->sum('protein_g'), 1),
            'total_fat'      => round(collect($formatted)->sum('fat_total_g'), 1),
            'total_fiber'    => round(collect($formatted)->sum('fiber_g'), 1),
            'total_sugar'    => round(collect($formatted)->sum('sugar_g'), 1),
        ]);
    }

    /**
     * Endpoint for analyzing a meal via a photo upload.
     */
    public function analyzeVision(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max limit
        ]);

        $items = NutritionService::analyzeImage($request->file('image'));

        // Format to what the frontend expects for meal_items
        $formatted = array_map(function ($item) {
            return [
                'food_name'        => $item['name']                    ?? 'Unknown',
                'quantity'         => $item['serving_size_g']          ?? 100,
                'quantity_unit'    => 'g',
                'calories_kcal'    => round($item['calories']                    ?? 0, 1),
                'carbs_g'          => round($item['carbohydrates_total_g']       ?? 0, 1),
                // Extended macros for the breakdown panel
                'fat_total_g'      => round($item['fat_total_g']                 ?? 0, 1),
                'fat_saturated_g'  => round($item['fat_saturated_g']            ?? 0, 1),
                'protein_g'        => round($item['protein_g']                   ?? 0, 1),
                'fiber_g'          => round($item['fiber_g']                     ?? 0, 1),
                'sugar_g'          => round($item['sugar_g']                     ?? 0, 1),
                'sodium_mg'        => round($item['sodium_mg']                   ?? 0, 0),
                'cholesterol_mg'   => round($item['cholesterol_mg']              ?? 0, 0),
            ];
        }, $items);

        return response()->json([
            'items'          => $formatted,
            'total_calories' => round(collect($formatted)->sum('calories_kcal'), 1),
            'total_carbs'    => round(collect($formatted)->sum('carbs_g'), 1),
            'total_protein'  => round(collect($formatted)->sum('protein_g'), 1),
            'total_fat'      => round(collect($formatted)->sum('fat_total_g'), 1),
            'total_fiber'    => round(collect($formatted)->sum('fiber_g'), 1),
            'total_sugar'    => round(collect($formatted)->sum('sugar_g'), 1),
        ]);
    }
}
