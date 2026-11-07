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

        $workoutResponse = $this->actingAs($user)
            ->postJson('/api/workouts', [
                'plan_name' => 'Home workout',
                'description' => 'A home training plan',
                'goal' => 'Build muscle',
                'level' => 'Beginner',
                'minutes_per_session' => '30',
                'duration' => '4 weeks',
                'notes' => 'Increase reps gradually',
                'exercises' => [
                    ['exercise_id' => $exerciseId],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('workout.plan_name', 'Home workout')
            ->assertJsonPath('workout.goal', 'Build muscle')
            ->assertJsonPath('workout.level', 'Beginner')
            ->assertJsonPath('workout.minutes_per_session', '30')
            ->assertJsonPath(
                'workout.exercises.0.best_for',
                'Build muscle, Lose weight, Improve endurance, Maintain performance'
            );

        $this->assertDatabaseHas('workouts', [
            'plan_name' => 'Home workout',
            'description' => 'A home training plan',
            'goal' => 'Build muscle',
            'level' => 'Beginner',
            'minutes_per_session' => '30',
            'duration' => '4 weeks',
            'notes' => 'Increase reps gradually',
        ]);

        $this->actingAs($user)
            ->patchJson('/api/workouts/'.$workoutResponse->json('workout.workout_id'), [
                'plan_name' => 'Updated home workout',
                'goal' => 'Improve endurance',
                'level' => 'Intermediate',
                'minutes_per_session' => '45',
            ])
            ->assertOk()
            ->assertJsonPath('workout.plan_name', 'Updated home workout')
            ->assertJsonPath('workout.goal', 'Improve endurance')
            ->assertJsonPath('workout.level', 'Intermediate')
            ->assertJsonPath('workout.minutes_per_session', '45');
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

    public function test_workout_exercises_are_saved_for_their_selected_weekdays(): void
    {
        $this->seed(ExerciseSeeder::class);
        $exerciseId = DB::table('exercises')->where('name', 'Push-Up')->value('exercise_id');
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/workouts', [
                'plan_name' => 'Weekly strength plan',
                'goal' => 'Build muscle',
                'level' => 'Beginner',
                'minutes_per_session' => '30',
                'duration' => '4 weeks',
                'exercises' => [
                    ['day' => 'Mon', 'exercise_id' => $exerciseId, 'sets' => '3', 'reps' => '10', 'notes' => 'Keep a steady pace', 'sort_order' => 0],
                    ['day' => 'Wed', 'exercise_id' => $exerciseId, 'sets' => '4', 'reps' => '8', 'sort_order' => 0],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('workout.exercises.0.day', 'Mon')
            ->assertJsonPath('workout.exercises.0.notes', 'Keep a steady pace')
            ->assertJsonPath('workout.exercises.1.day', 'Wed');

        $workoutId = $response->json('workout.workout_id');
        $this->assertDatabaseHas('workout_exercises', [
            'workout_id' => $workoutId,
            'exercise_id' => $exerciseId,
            'day' => 'Mon',
            'notes' => 'Keep a steady pace',
        ]);

        $this->actingAs($user)
            ->getJson('/api/workouts')
            ->assertOk()
            ->assertJsonPath('workouts.0.plan_name', 'Weekly strength plan')
            ->assertJsonPath('workouts.0.active', 1)
            ->assertJsonCount(2, 'workouts.0.exercises');

        $this->actingAs($user)
            ->deleteJson("/api/workouts/{$workoutId}")
            ->assertOk();
        $this->assertDatabaseMissing('workouts', ['workout_id' => $workoutId]);
    }

    public function test_exercise_catalog_requires_authentication(): void
    {
        $this->getJson('/api/exercises')->assertUnauthorized();
    }

    public function test_exercise_catalog_filters_by_one_goal_and_accepts_lost_weight_alias(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exercises?best_for=Lost%20Weight')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Treadmill Walk'])
            ->assertJsonMissing(['name' => 'Barbell Back Squat']);
    }

    public function test_exercise_catalog_accepts_the_maintain_fitness_goal_label(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exercises?best_for=Maintain%20Fitness')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Treadmill Walk']);
    }

    public function test_exercise_catalog_matches_any_selected_goal(): void
    {
        $this->seed(ExerciseSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exercises?best_for[]=Build%20Muscle&best_for[]=Lose%20Weight')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Barbell Back Squat'])
            ->assertJsonFragment(['name' => 'Treadmill Walk'])
            ->assertJsonMissing(['name' => 'Side Plank']);
    }

    public function test_exercise_catalog_rejects_unknown_goal_filters(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/exercises?best_for=Strength')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('best_for.0');
    }
}
