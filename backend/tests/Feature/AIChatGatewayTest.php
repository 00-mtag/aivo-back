<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AIChatGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_chat_forwards_prompt_and_history_to_gateway(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        Http::fake(fn (Request $request) => Http::response([
            'success' => true,
            'message' => str_contains($request['messages'][0]['content'], 'Create a concise')
                ? '20-Minute Workout Ideas'
                : 'Try a short walk.',
        ]));

        $this->actingAs(User::factory()->create())
            ->postJson('/api/ai/chat', [
                'system_prompt' => 'You are AIVO.',
                'message' => 'What should I do today?',
                'messages' => [
                    ['role' => 'user', 'content' => 'I have 20 minutes.'],
                    ['role' => 'assistant', 'content' => 'What equipment do you have?'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Try a short walk.')
            ->assertJsonPath('title', '20-Minute Workout Ideas')
            ->assertJsonStructure(['conversation_id']);

        $conversationId = DB::table('conversations')->value('conversation_id');
        $this->assertDatabaseHas('conversations', [
            'conversation_id' => $conversationId,
            'title' => '20-Minute Workout Ideas',
        ]);
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'assistant',
            'content' => 'Try a short walk.',
        ]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://gateway.example/api/ai/chat'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && count($request['messages']) === 4
            && $request['messages'][0] === ['role' => 'system', 'content' => 'You are AIVO.']
            && $request['messages'][1] === ['role' => 'user', 'content' => 'I have 20 minutes.']
            && $request['messages'][2] === ['role' => 'assistant', 'content' => 'What equipment do you have?']
            && $request['messages'][3] === ['role' => 'user', 'content' => 'What should I do today?']
        );
        Http::assertSent(fn (Request $request) => $request->url() === 'https://gateway.example/api/ai/chat'
            && $request['messages'][0]['content'] === 'Create a concise, accurate title for this conversation in 3 to 7 words. Return only the title, with no quotes or explanation.'
            && $request['messages'][1] === ['role' => 'user', 'content' => 'What should I do today?']
        );
    }

    public function test_chat_requires_gateway_configuration(): void
    {
        config(['services.ai_gateway.url' => null, 'services.ai_gateway.key' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/ai/chat', [
                'system_prompt' => 'You are AIVO.',
                'message' => 'Hello',
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'The AI chat service is not configured.');
    }

    public function test_chat_saves_later_messages_to_the_owned_conversation(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        Http::fake([
            'gateway.example/*' => Http::response(['message' => 'Add a gentle warm-up.']),
        ]);
        $user = User::factory()->create();
        $conversationId = DB::table('conversations')->insertGetId([
            'user_id' => $user->user_id,
            'title' => 'Workout help',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'conversation_id');

        $this->actingAs($user)
            ->postJson('/api/ai/chat', [
                'system_prompt' => 'You are AIVO.',
                'message' => 'How should I start?',
                'conversation_id' => $conversationId,
                'messages' => [['role' => 'user', 'content' => 'I want to work out.']],
            ])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversationId);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_workout_plan_uses_only_goal_and_database_exercises_when_profile_sharing_is_declined(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Bodyweight Squat',
            'muscle_group' => 'Legs',
            'equipment' => 'None',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $secondExerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Glute Bridge',
            'muscle_group' => 'Glutes',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $user = User::factory()->create();
        DB::table('user_profiles')->insert([
            'user_id' => $user->user_id,
            'age' => 28,
            'height' => '170 cm',
            'weight' => '70 kg',
            'activity_level' => 'Moderate',
            'address' => 'Private address',
            'dietary_preferences' => 'Private preference',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('health_conditions')->insert([
            'user_id' => $user->user_id,
            'condition_name' => 'Private health condition',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(fn () => Http::response(['message' => json_encode([
            'name' => 'Starter Strength',
            'level' => 'Beginner',
            'exercises' => [
                ['exercise_id' => $exerciseId, 'day' => 'Mon', 'sets' => 3, 'reps' => '10'],
                ['exercise_id' => $secondExerciseId, 'day' => 'Mon', 'sets' => 3, 'reps' => '12'],
            ],
        ])]));

        $this->actingAs($user)
            ->postJson('/api/ai/workout-plan', [
                'goal' => 'Build muscle',
                'include_profile' => false,
                'schedule_days' => ['Mon'],
            ])
            ->assertOk()
            ->assertJsonPath('plan.goal', 'Build muscle')
            ->assertJsonPath('plan.exercises.0.exercise_id', $exerciseId)
            ->assertJsonPath('plan.exercises.0.name', 'Bodyweight Squat')
            ->assertJsonCount(2, 'plan.exercises');

        Http::assertSent(fn (Request $request) => $request['messages'][1]['content'] !== ''
            && str_contains($request['messages'][1]['content'], '"goal":"Build muscle"')
            && str_contains($request['messages'][1]['content'], '"profile":[]')
            && str_contains($request['messages'][1]['content'], '"schedule_days":["Mon"]')
            && str_contains($request['messages'][1]['content'], '"name":"Bodyweight Squat"')
            && ! str_contains($request['messages'][1]['content'], '70 kg')
            && ! str_contains($request['messages'][1]['content'], 'Private address')
            && ! str_contains($request['messages'][1]['content'], 'Private preference')
            && ! str_contains($request['messages'][1]['content'], 'Private health condition')
        );
    }

    public function test_workout_plan_includes_only_approved_profile_fields_and_rejects_non_catalog_exercises(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Push-up',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $user = User::factory()->create();
        DB::table('user_profiles')->insert([
            'user_id' => $user->user_id,
            'age' => 28,
            'height' => '170 cm',
            'weight' => '70 kg',
            'activity_level' => 'Moderate',
            'address' => 'Private address',
            'dietary_preferences' => 'Private preference',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('health_conditions')->insert([
            'user_id' => $user->user_id,
            'condition_name' => 'Knee condition',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(fn (Request $request) => Http::response(['message' => json_encode([
            'name' => 'Starter Strength',
            'level' => 'Beginner',
            'exercises' => [['exercise_id' => $exerciseId + 999, 'day' => 'Mon', 'sets' => 3, 'reps' => '10']],
        ])]));

        $this->actingAs($user)
            ->postJson('/api/ai/workout-plan', [
                'goal' => 'Build muscle',
                'include_profile' => true,
                'schedule_days' => ['Mon'],
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI service returned an invalid workout plan. Please try again.');

        Http::assertSent(fn (Request $request) => str_contains($request['messages'][1]['content'], '"age":28')
            && str_contains($request['messages'][1]['content'], '"schedule_days":["Mon"]')
            && str_contains($request['messages'][1]['content'], '"height":"170 cm"')
            && str_contains($request['messages'][1]['content'], '"weight":"70 kg"')
            && str_contains($request['messages'][1]['content'], '"activity_level":"Moderate"')
            && str_contains($request['messages'][1]['content'], '"health_conditions":["Knee condition"]')
            && ! str_contains($request['messages'][1]['content'], 'Private address')
            && ! str_contains($request['messages'][1]['content'], 'Private preference')
        );
    }

    public function test_workout_plan_rejects_more_than_three_exercises_per_selected_day(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $exerciseIds = [];
        foreach (['Squat', 'Lunge', 'Bridge', 'Step-up'] as $name) {
            $exerciseIds[] = DB::table('exercises')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'exercise_id');
        }
        Http::fake(fn () => Http::response(['message' => json_encode([
            'name' => 'Leg Day',
            'exercises' => array_map(fn ($exerciseId) => [
                'exercise_id' => $exerciseId,
                'day' => 'Mon',
                'sets' => 3,
                'reps' => '10',
            ], $exerciseIds),
        ])]));

        $this->actingAs(User::factory()->create())
            ->postJson('/api/ai/workout-plan', [
                'goal' => 'Build muscle',
                'include_profile' => false,
                'schedule_days' => ['Mon'],
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI service returned an invalid workout plan. Please try again.');
    }

    public function test_workout_plan_requires_multiple_exercises_on_each_selected_day(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $exerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Squat',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        Http::fake(fn () => Http::response(['message' => json_encode([
            'name' => 'Leg Day',
            'exercises' => [['exercise_id' => $exerciseId, 'day' => 'Mon', 'sets' => 3, 'reps' => '10']],
        ])]));

        $this->actingAs(User::factory()->create())
            ->postJson('/api/ai/workout-plan', [
                'goal' => 'Build muscle',
                'include_profile' => false,
                'schedule_days' => ['Mon'],
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'The AI service returned an invalid workout plan. Please try again.');
    }

    public function test_chat_reports_a_non_json_gateway_response_as_a_url_configuration_error(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        Http::fake([
            'gateway.example/*' => Http::response('<html>Hosting dashboard</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson('/api/ai/chat', [
                'system_prompt' => 'You are AIVO.',
                'message' => 'Hello',
            ])
            ->assertStatus(502)
            ->assertJsonPath(
                'message',
                'The AI gateway returned a non-JSON response. Check AI_GATEWAY_URL points to the API host and /api/ai/chat path.'
            );
    }
}
