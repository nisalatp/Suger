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

    public static function analyzeImage($file): array
    {
        $apiKey = config('services.openai.key');
        $baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');

        if (empty($apiKey)) {
            Log::warning('OpenAI API key missing for vision.');
            return self::getMockData('Camera Upload Meal');
        }

        $base64 = base64_encode(file_get_contents($file->path()));
        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post(rtrim($baseUrl, '/') . '/chat/completions', [
                    'model' => env('OPENAI_MODEL', 'gpt-4o'), // Deepseek uses deepseek-chat
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert nutritionist. Analyze this meal image. Identify all visible food items and estimate their serving sizes in grams. Return ONLY a valid JSON object with a single key "items" containing an array of objects. Each object MUST have: "name" (string), "calories" (number), "serving_size_g" (number), "fat_total_g" (number), "fat_saturated_g" (number), "protein_g" (number), "sodium_mg" (number), "potassium_mg" (number), "cholesterol_mg" (number), "carbohydrates_total_g" (number), "fiber_g" (number), "sugar_g" (number).'
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64}"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                if ($content) {
                    $data = json_decode($content, true);
                    if (isset($data['items']) && is_array($data['items'])) {
                        return $data['items'];
                    }
                }
            }

            Log::error('Vision API Error: ' . $response->body());
        } catch (\Exception $e) {
            Log::error('Vision API Exception: ' . $e->getMessage());
        }

        return self::getMockData('Camera Upload Meal');
    }

    /**
     * Fallback mock data when API key is missing or request fails.
     */
    private static function getMockData(string $query): array
    {
        $words = str_word_count($query);
        return [
            [
                'name'                    => trim($query),
                'calories'                => rand(150, 450),
                'serving_size_g'          => 100,
                'fat_total_g'             => rand(3, 18),
                'fat_saturated_g'         => rand(1, 5),
                'protein_g'               => rand(5, 30),
                'sodium_mg'               => rand(50, 400),
                'potassium_mg'            => rand(50, 300),
                'cholesterol_mg'          => rand(0, 80),
                'carbohydrates_total_g'   => rand(10, 60),
                'fiber_g'                 => rand(0, 8),
                'sugar_g'                 => rand(0, 25),
            ]
        ];
    }
}
