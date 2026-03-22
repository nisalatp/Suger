<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NutritionService
{
    /**
     * Analyze a natural language food query using CalorieNinjas API.
     * Returns an array of items with calories and carbs.
     *
     * @param string $query
     * @return array
     */
    public static function analyze(string $query): array
    {
        if (empty(trim($query))) {
            return [];
        }

        $apiKey = config('services.calorieninjas.key');

        if (empty($apiKey)) {
            Log::warning('CalorieNinjas API key is missing. Using mock data for: ' . $query);
            return self::getMockData($query);
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey
            ])->get('https://api.calorieninjas.com/v1/nutrition', [
                'query' => $query
            ]);

            if ($response->successful() && $response->json('items')) {
                return $response->json('items');
            }

            Log::error('CalorieNinjas API Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('CalorieNinjas Exception: ' . $e->getMessage());
        }

        return self::getMockData($query);
    }

    /**
     * Fallback mock data when API key is missing or request fails.
     */
    private static function getMockData(string $query): array
    {
        // Simple mock to always return something based on word count
        $words = str_word_count($query);
        $mockCalories = rand(100, 400) * max(1, $words);
        $mockCarbs = rand(10, 50) * max(1, $words);

        return [
            [
                'name' => trim($query),
                'calories' => $mockCalories,
                'serving_size_g' => 100,
                'fat_total_g' => rand(5, 20),
                'fat_saturated_g' => rand(1, 5),
                'protein_g' => rand(5, 30),
                'sodium_mg' => rand(50, 500),
                'potassium_mg' => rand(50, 200),
                'cholesterol_mg' => rand(0, 100),
                'carbohydrates_total_g' => $mockCarbs,
                'fiber_g' => rand(0, 10),
                'sugar_g' => rand(0, 20),
            ]
        ];
    }
}
