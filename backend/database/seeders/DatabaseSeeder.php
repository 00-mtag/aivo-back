<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(ExerciseSeeder::class);

        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'test-user',
            'email' => 'test@example.com',
        ]);
    }
}
