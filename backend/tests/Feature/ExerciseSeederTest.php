<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExerciseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_database_seeder_includes_the_exercise_catalog(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('exercises', [
            'name' => 'Push-Up',
        ]);
        $this->assertDatabaseHas('exercises', [
            'name' => 'Barbell Back Squat',
        ]);
    }

    public function test_it_seeds_a_reusable_catalog_of_home_and_gym_exercises(): void
    {
        $seeder = new ExerciseSeeder;
        $seeder->run();
        $seededCount = DB::table('exercises')->count();

        $this->assertGreaterThanOrEqual(60, $seededCount);
        $this->assertDatabaseHas('exercises', [
            'name' => 'Push-Up',
            'equipment' => 'Bodyweight (home or gym)',
            'best_for' => 'Build muscle, Lose weight, Improve endurance, Maintain performance',
        ]);
        $this->assertDatabaseHas('exercises', [
            'name' => 'Barbell Back Squat',
            'equipment' => 'Barbell and squat rack',
        ]);
        $this->assertDatabaseHas('exercises', [
            'name' => 'Resistance Band Row',
            'equipment' => 'Resistance band and secure anchor',
        ]);
        $this->assertDatabaseHas('exercises', [
            'name' => 'Treadmill Walk',
            'best_for' => 'Lose weight, Improve endurance, Maintain performance',
        ]);

        $seeder->run();

        $this->assertSame($seededCount, DB::table('exercises')->count());
        $this->assertSame(
            $seededCount,
            DB::table('exercises')->whereNotNull('best_for')->count()
        );
    }

    public function test_workout_responses_include_seeded_exercise_goals(): void
    {
        $this->seed(ExerciseSeeder::class);
        $exerciseId = DB::table('exercises')
            ->where('name', 'Push-Up')
            ->value('exercise_id');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/workouts', [
                'name' => 'Home workout',
                'exercises' => [
                    ['exercise_id' => $exerciseId],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'workout.exercises.0.best_for',
                'Build muscle, Lose weight, Improve endurance, Maintain performance'
            );
    }

    public function test_authenticated_users_can_retrieve_the_exercise_catalog(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exercises')
            ->assertOk()
            ->assertJsonCount(73, 'exercises')
            ->assertJsonPath('exercises.0.name', 'Assisted Pull-Up')
            ->assertJsonPath('exercises.0.best_for', 'Build muscle, Maintain performance')
            ->assertJsonStructure([
                'exercises' => [
                    '*' => [
                        'exercise_id',
                        'name',
                        'description',
                        'muscle_group',
                        'equipment',
                        'difficulty',
                        'instructions',
                        'best_for',
                    ],
                ],
            ]);
    }

    public function test_exercise_catalog_requires_authentication(): void
    {
        $this->getJson('/api/exercises')->assertUnauthorized();
    }
}
