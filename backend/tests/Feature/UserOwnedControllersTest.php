<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserOwnedControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_read_and_update_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DB::table('user_profiles')->insert([
            'user_id' => $otherUser->user_id,
            'phone_number' => '0000000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'phone_number' => '09171234567',
                'user_id' => $otherUser->user_id,
            ])
            ->assertOk()
            ->assertJsonPath('profile.user_id', $user->user_id)
            ->assertJsonPath('profile.phone_number', '09171234567');

        $this->actingAs($user)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('profile.user_id', $user->user_id);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $otherUser->user_id,
            'phone_number' => '0000000000',
        ]);
    }

    public function test_authenticated_user_can_create_and_read_ai_conversations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/ai-chat/conversations', [
            'title' => 'Training plan',
            'content' => 'Help me plan a workout.',
        ])->assertCreated()
            ->assertJsonPath('conversation.title', 'Training plan')
            ->assertJsonPath('conversation.messages.0.sender', 'user')
            ->assertJsonPath('conversation.messages.0.content', 'Help me plan a workout.');

        $conversationId = DB::table('conversations')
            ->where('user_id', $user->user_id)
            ->value('conversation_id');
        $otherConversationId = DB::table('conversations')->insertGetId([
            'user_id' => $otherUser->user_id,
            'title' => 'Private conversation',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'conversation_id');

        $this->getJson('/api/ai-chat/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'conversations');
        $this->getJson("/api/ai-chat/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('conversation.user_id', $user->user_id);
        $this->getJson("/api/ai-chat/conversations/{$otherConversationId}")->assertNotFound();
        $this->postJson('/api/ai-chat/conversations/999999/messages', [
            'content' => 'This conversation is not mine.',
        ])->assertNotFound();
    }

    public function test_nutrition_progress_and_workout_records_are_user_scoped(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        $foodId = DB::table('food')->insertGetId([
            'name' => 'Oats',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');

        $mealResponse = $this->postJson('/api/nutrition/meals', [
            'meal_type' => 'breakfast',
            'meal_date' => '2026-10-30',
            'items' => [
                ['food_id' => $foodId, 'quantity' => 1, 'calories' => 150],
            ],
        ])->assertCreated()
            ->assertJsonPath('meal.user_id', $user->user_id)
            ->assertJsonPath('meal.items.0.food_name', 'Oats');
        $mealId = $mealResponse->json('meal.meal_id');

        $this->patchJson("/api/nutrition/meals/{$mealId}", [
            'notes' => 'Before training',
        ])->assertOk()
            ->assertJsonPath('meal.notes', 'Before training');
        $this->getJson('/api/nutrition/meals')->assertOk()->assertJsonCount(1, 'meals');
        $goalResponse = $this->postJson('/api/nutrition/goals', [
            'daily_calories' => 2100,
            'start_date' => '2026-10-30',
            'user_id' => $otherUser->user_id,
        ])->assertCreated()
            ->assertJsonPath('nutrition_goal.user_id', $user->user_id);
        $goalId = $goalResponse->json('nutrition_goal.goal_id');
        $this->patchJson("/api/nutrition/goals/{$goalId}", [
            'daily_calories' => 2200,
        ])->assertOk()
            ->assertJsonPath('nutrition_goal.daily_calories', 2200);

        $progressResponse = $this->postJson('/api/progress', [
            'record_date' => '2026-10-30',
            'weight' => 72.5,
            'user_id' => $otherUser->user_id,
        ])->assertCreated()
            ->assertJsonPath('progress_record.user_id', $user->user_id);
        $progressId = $progressResponse->json('progress_record.progress_id');
        $this->patchJson("/api/progress/{$progressId}", [
            'weight' => 71.8,
        ])->assertOk()
            ->assertJsonPath('progress_record.weight', 71.8);

        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Squat',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $workoutResponse = $this->postJson('/api/workouts', [
            'name' => 'Leg day',
            'exercises' => [
                ['exercise_id' => $exerciseId, 'sets' => '3', 'reps' => '10'],
            ],
        ])->assertCreated()
            ->assertJsonPath('workout.user_id', $user->user_id)
            ->assertJsonPath('workout.exercises.0.name', 'Squat');
        $workoutId = $workoutResponse->json('workout.workout_id');
        $this->patchJson("/api/workouts/{$workoutId}", [
            'name' => 'Updated leg day',
        ])->assertOk()
            ->assertJsonPath('workout.name', 'Updated leg day');
        $this->getJson('/api/workouts')->assertOk()->assertJsonCount(1, 'workouts');

        $this->actingAs($otherUser)
            ->patchJson("/api/nutrition/meals/{$mealId}", ['notes' => 'Unauthorized'])
            ->assertNotFound();
        $this->patchJson("/api/nutrition/goals/{$goalId}", ['daily_calories' => 1])
            ->assertNotFound();
        $this->patchJson("/api/progress/{$progressId}", ['weight' => 1])
            ->assertNotFound();
        $this->patchJson("/api/workouts/{$workoutId}", ['name' => 'Unauthorized'])
            ->assertNotFound();
    }
}
