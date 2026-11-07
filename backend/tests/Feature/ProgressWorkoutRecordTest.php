<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProgressWorkoutRecordTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonPath('progress_workout_record.exerices_skipped', '1');

        $this->assertDatabaseHas('progress_workout_records', [
            'user_id' => $user->user_id,
            'workout_id' => $workoutId,
            'workout_name' => 'Strength plan',
            'workout_type' => 'Monday',
            'workout_duration' => '1380',
            'workout_intensity' => 'Intermediate',
            'exercises_done' => '4',
            'exerices_skipped' => '1',
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
}
