<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgressWorkoutRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_index_returns_only_the_authenticated_users_persisted_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $workoutId = DB::table('workouts')->insertGetId([
            'user_id' => $user->user_id,
            'plan_name' => 'Strength plan',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_id');
        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Squat',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        DB::table('progress_records')->insert([
            'user_id' => $user->user_id,
            'record_date' => now()->toDateString(),
            'weight' => 72.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('progress_workout_records')->insert([
            'user_id' => $user->user_id,
            'workout_id' => $workoutId,
            'workout_name' => 'Strength plan',
            'workout_duration' => '1800',
            'exercises_done' => '3',
            'exercises_skipped' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('calorie_burned_records')->insert([
            'user_id' => $user->user_id,
            'workout_id' => $workoutId,
            'exercise_id' => $exerciseId,
            'calories_burned' => '12.5',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('progress_records')->insert([
            'user_id' => $otherUser->user_id,
            'record_date' => now()->toDateString(),
            'weight' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/progress')
            ->assertOk()
            ->assertJsonCount(1, 'progress_records')
            ->assertJsonCount(1, 'workout_records')
            ->assertJsonCount(1, 'calorie_burned_records')
            ->assertJsonPath('progress_records.0.weight', 72.5)
            ->assertJsonPath('workout_records.0.exercises_done', '3')
            ->assertJsonPath('calorie_burned_records.0.exercise_name', 'Squat')
            ->assertJsonCount(0, 'calorie_intake_records');
    }

    public function test_finishing_a_workout_saves_a_user_owned_progress_record(): void
    {
        $user = User::factory()->create();
        $workoutId = DB::table('workouts')->insertGetId([
            'user_id' => $user->user_id,
            'plan_name' => 'Strength plan',
            'goal' => 'Build muscle',
            'level' => 'Intermediate',
            'minutes_per_session' => '45',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_id');

        $this->actingAs($user)
            ->postJson('/api/progress/workouts', [
                'workout_id' => $workoutId,
                'workout_type' => 'Monday',
                'workout_duration' => 1380,
                'exercises_done' => 4,
                'exercises_skipped' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('progress_workout_record.user_id', $user->user_id)
            ->assertJsonPath('progress_workout_record.workout_id', $workoutId)
            ->assertJsonPath('progress_workout_record.workout_duration', '1380')
            ->assertJsonPath('progress_workout_record.exercises_done', '4')
            ->assertJsonPath('progress_workout_record.exercises_skipped', '1');

        $this->assertDatabaseHas('progress_workout_records', [
            'user_id' => $user->user_id,
            'workout_id' => $workoutId,
            'workout_name' => 'Strength plan',
            'workout_type' => 'Monday',
            'workout_duration' => '1380',
            'workout_intensity' => 'Intermediate',
            'exercises_done' => '4',
            'exercises_skipped' => '1',
        ]);
    }

    public function test_a_user_cannot_record_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $workoutId = DB::table('workouts')->insertGetId([
            'user_id' => $owner->user_id,
            'plan_name' => 'Private plan',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_id');

        $this->actingAs($otherUser)
            ->postJson('/api/progress/workouts', [
                'workout_id' => $workoutId,
                'workout_duration' => 60,
                'exercises_done' => 1,
                'exercises_skipped' => 0,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('progress_workout_records', 0);
    }

    public function test_finishing_a_workout_estimates_and_saves_calories_for_each_exercise(): void
    {
        $user = User::factory()->create();
        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Jump Rope',
            'weightORduration' => 'duration',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $workoutId = DB::table('workouts')->insertGetId([
            'user_id' => $user->user_id,
            'plan_name' => 'Cardio plan',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_id');
        $workoutExerciseId = DB::table('workout_exercises')->insertGetId([
            'workout_id' => $workoutId,
            'exercise_id' => $exerciseId,
            'day' => 'Mon',
            'sets' => '3',
            'reps' => '10',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_exercise_id');

        $this->actingAs($user)
            ->postJson('/api/progress/workouts', [
                'workout_id' => $workoutId,
                'workout_type' => 'Monday',
                'workout_duration' => 60,
                'exercises_done' => 1,
                'exercises_skipped' => 0,
                'body_weight_kg' => 70,
                'exercise_durations' => [[
                    'workout_exercise_id' => $workoutExerciseId,
                    'duration_seconds' => 60,
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('calorie_burned_records.0.exercise_id', $exerciseId)
            ->assertJsonPath('calorie_burned_records.0.duration_seconds', 60)
            ->assertJsonPath('calorie_burned_records.0.calories_burned', 12.25)
            ->assertJsonPath('total_calories_burned', 12.25);

        $this->assertDatabaseHas('calorie_burned_records', [
            'user_id' => $user->user_id,
            'workout_id' => $workoutId,
            'exercise_id' => $exerciseId,
            'calories_burned' => '12.25',
        ]);
    }

    public function test_calorie_estimate_requires_body_weight_when_profile_has_none(): void
    {
        $user = User::factory()->create();
        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Squat',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $workoutId = DB::table('workouts')->insertGetId([
            'user_id' => $user->user_id,
            'plan_name' => 'Strength plan',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_id');
        $workoutExerciseId = DB::table('workout_exercises')->insertGetId([
            'workout_id' => $workoutId,
            'exercise_id' => $exerciseId,
            'day' => 'Mon',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'workout_exercise_id');

        $this->actingAs($user)
            ->postJson('/api/progress/workouts', [
                'workout_id' => $workoutId,
                'workout_type' => 'Monday',
                'workout_duration' => 60,
                'exercises_done' => 1,
                'exercises_skipped' => 0,
                'exercise_durations' => [[
                    'workout_exercise_id' => $workoutExerciseId,
                    'duration_seconds' => 60,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body_weight_kg');

        $this->assertDatabaseCount('progress_workout_records', 0);
        $this->assertDatabaseCount('calorie_burned_records', 0);
    }
}
