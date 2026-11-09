<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiNutritionWeekPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_week_plan_uses_database_foods_and_saves_meals_and_weekly_calorie_goal(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DB::table('user_profiles')->insert([
            'user_id' => $user->user_id,
            'dietary_preferences' => 'Vegetarian',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_profiles')->insert([
            'user_id' => $otherUser->user_id,
            'dietary_preferences' => 'Private dietary preference',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('food_allergies')->insert([
            [
                'user_id' => $user->user_id,
                'allergy_name' => 'Peanuts',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $otherUser->user_id,
                'allergy_name' => 'Private allergy',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $foodId = DB::table('food')->insertGetId([
            'name' => 'Rice bowl',
            'ingredients' => 'Rice, salt (optional).',
            'calories' => 500,
            'protein' => 25,
            'carbohydrates' => 60,
            'fat' => 10,
            'serving_size' => '1',
            'serving_unit' => 'bowl',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');
        DB::table('food')->insert([
            'uploaded_by' => (string) $otherUser->user_id,
            'name' => 'Other user private food',
            'calories' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = Carbon::parse('2026-11-09')->addDays($offset)->toDateString();
            $days[] = [
                'date' => $date,
                'meals' => array_map(fn ($type) => [
                    'meal_type' => $type,
                    'items' => [['food_id' => $foodId, 'quantity' => 1]],
                ], ['Breakfast', 'Lunch', 'Dinner', 'Snack']),
            ];
        }
        Http::fake(fn () => Http::response(['message' => json_encode(['days' => $days])]));

        $this->actingAs($user)
            ->postJson('/api/nutrition/ai-week-plan', [
                'daily_calories' => 2050,
                'start_date' => '2026-11-09',
                'goal' => 'Maintain fitness',
            ])
            ->assertOk()
            ->assertJsonCount(28, 'meals')
            ->assertJsonPath('nutrition_goal.daily_calories', 2050)
            ->assertJsonPath('meals.0.items.0.food_id', $foodId)
            ->assertJsonPath('meals.0.items.0.calories', 500)
            ->assertJsonPath('meals.0.items.0.protein', 25);

        $this->assertDatabaseCount('meals', 28);
        $this->assertDatabaseCount('meal_items', 28);
        $this->assertDatabaseHas('nutrition_goal', [
            'user_id' => $user->user_id,
            'daily_calories' => 2050,
            'start_date' => '2026-11-09 00:00:00',
            'end_date' => '2026-11-15 23:59:59',
        ]);
        Http::assertSent(function (Request $request) {
            $prompt = json_decode($request['messages'][1]['content'], true);

            return $prompt['daily_calorie_target'] === 2050
                && $prompt['description'] === 'Recommend daily kcal/day for Maintain fitness = 2050 kcal'
                && $prompt['food_catalog'][0]['name'] === 'Rice bowl'
                && $prompt['food_catalog'][0]['ingredients'] === 'Rice, salt (optional).'
                && $prompt['allergies'] === ['Peanuts']
                && $prompt['dietary_preferences'] === 'Vegetarian'
                && ! str_contains($request['messages'][1]['content'], 'Private allergy')
                && ! str_contains($request['messages'][1]['content'], 'Private dietary preference')
                && ! str_contains($request['messages'][1]['content'], 'Other user private food');
        });
    }

    public function test_ai_week_plan_rejects_foods_not_in_the_user_available_catalog(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DB::table('food')->insert([
            'name' => 'Available food',
            'calories' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignFoodId = DB::table('food')->insertGetId([
            'uploaded_by' => (string) $otherUser->user_id,
            'name' => 'Private food',
            'calories' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');
        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $days[] = [
                'date' => Carbon::parse('2026-11-09')->addDays($offset)->toDateString(),
                'meals' => array_map(fn ($type) => [
                    'meal_type' => $type,
                    'items' => [['food_id' => $foreignFoodId, 'quantity' => 1]],
                ], ['Breakfast', 'Lunch', 'Dinner', 'Snack']),
            ];
        }
        Http::fake(fn () => Http::response(['message' => json_encode(['days' => $days])]));

        $this->actingAs($user)
            ->postJson('/api/nutrition/ai-week-plan', [
                'daily_calories' => 2000,
                'start_date' => '2026-11-09',
                'goal' => 'Maintain fitness',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI meal plan included a food that is not available in your catalog. Please try again.');

        $this->assertDatabaseCount('meals', 0);
        $this->assertDatabaseCount('nutrition_goal', 0);
    }

    public function test_ai_week_plan_adjusts_servings_to_meet_the_daily_calorie_range(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $foodId = DB::table('food')->insertGetId([
            'name' => 'Plain rice serving',
            'ingredients' => 'Rice.',
            'calories' => 100,
            'protein' => 2,
            'carbohydrates' => 22,
            'fat' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $days[] = [
                'date' => Carbon::parse('2026-11-09')->addDays($offset)->toDateString(),
                'meals' => array_map(fn ($type) => [
                    'meal_type' => $type,
                    'items' => [['food_id' => $foodId, 'quantity' => 1]],
                ], ['Breakfast', 'Lunch', 'Dinner', 'Snack']),
            ];
        }
        Http::fake(fn () => Http::response(['message' => json_encode(['days' => $days])]));

        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/nutrition/ai-week-plan', [
                'daily_calories' => 2000,
                'start_date' => '2026-11-09',
                'goal' => 'Maintain fitness',
            ])
            ->assertOk()
            ->assertJsonCount(28, 'meals');

        foreach (array_chunk($response->json('meals'), 4) as $dayMeals) {
            $dailyCalories = array_sum(array_map(
                fn ($meal) => array_sum(array_column($meal['items'], 'calories')),
                $dayMeals
            ));
            $this->assertGreaterThanOrEqual(1600, $dailyCalories);
            $this->assertLessThanOrEqual(2400, $dailyCalories);
        }
        $this->assertDatabaseCount('meals', 28);
        $this->assertDatabaseCount('meal_items', 28);
    }

    public function test_ai_week_plan_reports_gateway_rejection_status_without_exposing_request_data(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        DB::table('food')->insert([
            'name' => 'Rice',
            'ingredients' => 'Rice, water.',
            'calories' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(fn () => Http::response(['error' => 'Request too large'], 413));

        $this->actingAs(User::factory()->create())
            ->postJson('/api/nutrition/ai-week-plan', [
                'daily_calories' => 2000,
                'start_date' => '2026-11-09',
                'goal' => 'Maintain fitness',
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The meal-plan request is too large for the AI service. Reduce the food catalog and try again.');
    }

    public function test_ai_week_plan_enforces_gender_aware_calorie_minimums_and_absolute_maximum(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        DB::table('food')->insert([
            'name' => 'Rice',
            'calories' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            ['gender' => 'Female', 'calories' => 1199, 'minimum' => 1200],
            ['gender' => 'Male', 'calories' => 1499, 'minimum' => 1500],
            ['gender' => 'Prefer not to say', 'calories' => 1499, 'minimum' => 1500],
        ] as $case) {
            $user = User::factory()->create();
            DB::table('user_profiles')->insert([
                'user_id' => $user->user_id,
                'gender' => $case['gender'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($user)
                ->postJson('/api/nutrition/ai-week-plan', [
                    'daily_calories' => $case['calories'],
                    'start_date' => '2026-11-09',
                    'goal' => 'Maintain fitness',
                ])
                ->assertStatus(422)
                ->assertJsonPath('errors.daily_calories.0', "The daily calorie target must be at least {$case['minimum']} kcal.");
        }

        $this->actingAs(User::factory()->create())
            ->postJson('/api/nutrition/ai-week-plan', [
                'daily_calories' => 5001,
                'start_date' => '2026-11-09',
                'goal' => 'Maintain fitness',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['daily_calories']);
    }
}
