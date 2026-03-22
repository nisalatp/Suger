<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Create or find user
        DB::table('users')->updateOrInsert(
            ['email' => 'nisalatp@gmail.com'],
            [
                'name' => 'Nisala Aloka Bandara',
                'given_name' => 'Nisala', 'family_name' => 'Bandara',
                'google_sub' => DB::table('users')->where('email', 'nisalatp@gmail.com')->value('google_sub') ?? 'google_test_sub_id',
                'public_id' => DB::table('users')->where('email', 'nisalatp@gmail.com')->value('public_id') ?? Str::ulid(),
                'email_verified' => true, 'timezone' => 'Asia/Colombo',
                'created_at' => $now, 'updated_at' => $now,
            ]
        );
        $userId = DB::table('users')->where('email', 'nisalatp@gmail.com')->value('id');

        // --- Complete user profile ---
        DB::table('user_profiles')->updateOrInsert(
            ['user_id' => $userId],
            [
                'date_of_birth' => '1995-06-15', 'gender' => 'male',
                'height_cm' => 175.00, 'weight_kg' => 78.50,
                'bmi' => round(78.5 / (1.75 * 1.75), 2),
                'diabetes_type' => 'type2', 'diagnosis_date' => '2023-08-10',
                'blood_group' => 'B+', 'created_at' => $now, 'updated_at' => $now,
            ]
        );

        // --- Consents ---
        foreach (['health_processing', 'terms', 'privacy'] as $type) {
            DB::table('consents')->updateOrInsert(
                ['user_id' => $userId, 'consent_type' => $type, 'revoked_at' => null],
                ['version' => '1.0', 'granted_at' => $now, 'ui_surface' => 'web', 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // --- Medications ---
        $metId = DB::table('medications')->insertGetId([
            'user_id' => $userId, 'name' => 'Metformin', 'route' => 'oral',
            'dose_value' => 500, 'dose_unit' => 'mg', 'is_insulin' => false,
            'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $glmId = DB::table('medications')->insertGetId([
            'user_id' => $userId, 'name' => 'Glimepiride', 'route' => 'oral',
            'dose_value' => 2, 'dose_unit' => 'mg', 'is_insulin' => false,
            'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- Doctor ---
        DB::table('doctors')->insert([
            'public_id' => Str::ulid(), 'user_id' => $userId,
            'full_name_enc' => encrypt('Dr. Kamal Perera'),
            'clinic_name_enc' => encrypt('Colombo Diabetes Centre'),
            'phone_enc' => encrypt('+94 11 234 5678'),
            'email_enc' => encrypt('dr.kamal@cdcentre.lk'),
            'address_enc' => encrypt('45 Ward Place, Colombo 07'),
            'is_primary' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // --- 90 days of data ---
        $glucoseRows = [];
        $medEventRows = [];
        $exerciseRows = [];

        $foods = [
            'breakfast' => [
                ['String Hoppers (5)', 30, 180], ['Roti & Curry', 40, 220],
                ['Milk Rice', 35, 200], ['Bread & Egg', 28, 190],
            ],
            'lunch' => [
                ['Rice & Curry', 65, 450], ['Fried Rice', 70, 520],
                ['Kottu Roti', 55, 480], ['Rice & Dhal', 60, 380],
            ],
            'dinner' => [
                ['Rice & Veg Curry', 50, 350], ['Noodles', 45, 400],
                ['Paratha & Chicken', 40, 420], ['Rice & Fish', 55, 370],
            ],
            'snack' => [
                ['Banana', 25, 105], ['Biscuits (3)', 18, 120],
                ['Kavum', 30, 200], ['Fruit Salad', 20, 90],
            ],
        ];
        $activities = ['walking', 'cycling', 'yoga', 'swimming', 'running'];
        $symptoms = [null, '["fatigue"]', '["thirst"]', null, null, '["blurred_vision"]', null, null];
        $tods = ['early_morning', 'morning', 'afternoon', 'evening', 'night'];

        for ($day = 89; $day >= 0; $day--) {
            $date = $now->copy()->subDays($day);

            // Fasting reading
            $fastVal = rand(85, 135);
            $fTime = $date->copy()->setTime(rand(6, 7), rand(0, 59));
            $glucoseRows[] = [
                'public_id' => Str::ulid(), 'user_id' => $userId,
                'measured_at_utc' => $fTime, 'measured_tz' => 'Asia/Colombo',
                'value_raw' => $fastVal, 'unit' => 'mg_dL', 'value_mgdl' => $fastVal,
                'time_of_day' => 'pre_breakfast', 'meal_type' => 'breakfast',
                'is_fasting' => true, 'source' => 'manual',
                'symptoms_json' => $symptoms[array_rand($symptoms)],
                'notes_enc' => null,
                'created_at' => $fTime, 'updated_at' => $fTime,
            ];

            // Pre & post meal for breakfast, lunch, dinner + maybe snack
            $meals = ['breakfast', 'lunch', 'dinner'];
            if (rand(0, 2) > 0) $meals[] = 'snack';

            foreach ($meals as $mt) {
                $hr = match ($mt) {
                    'breakfast' => rand(7, 8), 'lunch' => rand(12, 13),
                    'dinner' => rand(19, 20), 'snack' => rand(15, 16),
                };
                $tod = match ($mt) {
                    'breakfast' => 'pre_breakfast', 'lunch' => 'pre_lunch',
                    'dinner' => 'pre_dinner', 'snack' => 'other',
                };
                $todPost = match ($mt) {
                    'breakfast' => 'post_breakfast', 'lunch' => 'post_lunch',
                    'dinner' => 'post_dinner', 'snack' => 'other',
                };

                $preT = $date->copy()->setTime($hr, rand(0, 20));
                $mealT = $preT->copy()->addMinutes(rand(5, 15));
                $postT = $mealT->copy()->addMinutes(rand(90, 120));

                $preV = match ($mt) {
                    'breakfast' => $fastVal + rand(-5, 10),
                    default => rand(90, 155),
                };
                $postV = $preV + rand(30, 80);

                // Pre-meal glucose
                $glucoseRows[] = [
                    'public_id' => Str::ulid(), 'user_id' => $userId,
                    'measured_at_utc' => $preT, 'measured_tz' => 'Asia/Colombo',
                    'value_raw' => $preV, 'unit' => 'mg_dL', 'value_mgdl' => $preV,
                    'time_of_day' => $tod, 'meal_type' => $mt,
                    'is_fasting' => false, 'source' => 'manual',
                    'symptoms_json' => $symptoms[array_rand($symptoms)],
                    'notes_enc' => null,
                    'created_at' => $preT, 'updated_at' => $preT,
                ];

                // Post-meal glucose
                $glucoseRows[] = [
                    'public_id' => Str::ulid(), 'user_id' => $userId,
                    'measured_at_utc' => $postT, 'measured_tz' => 'Asia/Colombo',
                    'value_raw' => $postV, 'unit' => 'mg_dL', 'value_mgdl' => $postV,
                    'time_of_day' => $todPost, 'meal_type' => $mt,
                    'is_fasting' => false, 'source' => 'manual',
                    'symptoms_json' => $postV > 180 ? '["fatigue"]' : $symptoms[array_rand($symptoms)],
                    'notes_enc' => $postV > 200 ? encrypt('High spike after meal') : null,
                    'created_at' => $postT, 'updated_at' => $postT,
                ];

                // Meal log
                $food = $foods[$mt][array_rand($foods[$mt])];
                $mealId = DB::table('meals')->insertGetId([
                    'public_id' => Str::ulid(), 'user_id' => $userId,
                    'eaten_at_utc' => $mealT, 'eaten_tz' => 'Asia/Colombo',
                    'meal_type' => $mt, 'total_carbs_g' => $food[1],
                    'total_calories_kcal' => $food[2],
                    'created_at' => $mealT, 'updated_at' => $mealT,
                ]);
                DB::table('meal_items')->insert([
                    'meal_id' => $mealId, 'food_name' => $food[0],
                    'quantity' => 1, 'quantity_unit' => 'serving',
                    'carbs_g' => $food[1], 'calories_kcal' => $food[2],
                ]);
            }

            // Medication events
            $amT = $date->copy()->setTime(7, rand(0, 30));
            $pmT = $date->copy()->setTime(19, rand(0, 30));
            foreach ([
                [$metId, $amT, 500, 'mg'], [$metId, $pmT, 500, 'mg'],
                [$glmId, $amT, 2, 'mg'],
            ] as [$mid, $t, $dv, $du]) {
                $medEventRows[] = [
                    'public_id' => Str::ulid(), 'user_id' => $userId,
                    'medication_id' => $mid, 'taken_at_utc' => $t,
                    'dose_taken_value' => $dv, 'dose_taken_unit' => $du,
                    'created_at' => $t, 'updated_at' => $t,
                ];
            }

            // Exercise (4-5 times/week)
            if (rand(0, 6) < 5) {
                $exT = $date->copy()->setTime(rand(6, 18), rand(0, 59));
                $exerciseRows[] = [
                    'public_id' => Str::ulid(), 'user_id' => $userId,
                    'performed_at_utc' => $exT,
                    'activity_type' => $activities[array_rand($activities)],
                    'duration_minutes' => rand(20, 60),
                    'intensity' => ['low', 'moderate', 'high'][rand(0, 2)],
                    'created_at' => $exT, 'updated_at' => $exT,
                ];
            }
        }

        // Bulk insert
        foreach (array_chunk($glucoseRows, 100) as $c) DB::table('glucose_readings')->insert($c);
        foreach (array_chunk($medEventRows, 100) as $c) DB::table('medication_events')->insert($c);
        foreach (array_chunk($exerciseRows, 50) as $c) DB::table('exercises')->insert($c);

        $this->command->info('✅ ' . count($glucoseRows) . ' glucose readings seeded');
        $this->command->info('✅ 90 days of meals seeded (inline)');
        $this->command->info('✅ ' . count($medEventRows) . ' medication events seeded');
        $this->command->info('✅ ' . count($exerciseRows) . ' exercise logs seeded');
    }
}
