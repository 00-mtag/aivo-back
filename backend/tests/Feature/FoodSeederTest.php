<?php

namespace Tests\Feature;

use Database\Seeders\FoodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FoodSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_seeder_includes_the_food_catalog_and_ingredients(): void
    {
        $this->seed(FoodSeeder::class);

        $foodCount = DB::table('food')->count();
        $this->assertGreaterThanOrEqual(130, $foodCount);
        $this->assertDatabaseHas('food', [
            'name' => 'Adobong manok',
            'serving_size' => '1',
            'serving_unit' => 'serving',
        ]);
        $this->assertDatabaseHas('food', [
            'name' => 'Pandesal',
            'calories' => 130,
        ]);
        $this->assertDatabaseHas('food', [
            'name' => 'Lugaw',
            'ingredients' => 'Rice, water or broth, salt (optional), chicken meat (optional), ginger (optional).',
        ]);
        $this->assertDatabaseHas('food', [
            'name' => 'Century Tuna lite in water',
            'protein' => 26,
            'serving_unit' => 'can',
        ]);
        $this->assertDatabaseHas('food', [
            'name' => 'Beef steak',
            'serving_unit' => 'g',
        ]);
        $this->assertSame($foodCount, DB::table('food')->whereNotNull('calories')->count());
    }

    public function test_it_can_be_seeded_repeatedly_without_duplicating_foods(): void
    {
        $seeder = new FoodSeeder;
        $seeder->run();
        $foodCount = DB::table('food')->count();
        $seeder->run();

        $this->assertSame($foodCount, DB::table('food')->count());
    }
}
