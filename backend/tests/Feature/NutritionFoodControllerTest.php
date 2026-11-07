<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NutritionFoodControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_catalog_includes_shared_foods_and_only_the_current_users_custom_foods(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DB::table('food')->insert([
            [
                'uploaded_by' => null,
                'name' => 'Shared rice',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uploaded_by' => (string) $user->user_id,
                'name' => 'My custom meal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'uploaded_by' => (string) $otherUser->user_id,
                'name' => 'Private custom meal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/api/nutrition/foods')
            ->assertOk()
            ->assertJsonCount(2, 'foods')
            ->assertJsonFragment(['name' => 'Shared rice'])
            ->assertJsonFragment(['name' => 'My custom meal'])
            ->assertJsonMissing(['name' => 'Private custom meal']);
    }

    public function test_authenticated_user_can_create_a_custom_food(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/nutrition/foods', [
                'name' => 'Chicken bowl',
                'description' => 'Homemade',
                'calories' => 320,
                'protein' => 28,
                'carbohydrates' => 35,
                'fat' => 8,
                'serving_size' => '1',
                'serving_unit' => 'bowl',
            ])
            ->assertCreated()
            ->assertJsonPath('food.name', 'Chicken bowl')
            ->assertJsonPath('food.uploaded_by', (string) $user->user_id)
            ->assertJsonPath('food.calories', 320);

        $this->assertDatabaseHas('food', [
            'name' => 'Chicken bowl',
            'uploaded_by' => (string) $user->user_id,
        ]);
    }

    public function test_food_catalog_and_creation_require_authentication_and_validate_food_values(): void
    {
        $this->getJson('/api/nutrition/foods')->assertUnauthorized();
        $this->postJson('/api/nutrition/foods', [])->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/nutrition/foods', [
                'name' => '',
                'calories' => -1,
                'protein' => 0,
                'carbohydrates' => 0,
                'fat' => 0,
                'serving_size' => '1',
                'serving_unit' => 'serving',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'calories']);
    }
}
