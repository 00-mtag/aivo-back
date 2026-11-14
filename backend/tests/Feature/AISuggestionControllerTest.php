<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AISuggestionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_day_chat_suggestion_saves_all_scheduled_days_and_user_owned_exercises(): void
    {
        Http::preventStrayRequests();
        $pushUpId = DB::table('exercises')->insertGetId([
            'name' => 'Push-Up',
            'muscle_group' => 'Chest',
            'weightORduration' => 'weight',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $exerciseNamesByDay = [
            'Mon' => ['Push-Up', 'Incline Push-Up', 'Dumbbell Floor Press', 'Shoulder Press', 'Lateral Raise', 'Bicep Curl', 'Triceps Dip'],
            'Wed' => ['Squat', 'Reverse Lunge', 'Glute Bridge', 'Calf Raise', 'Romanian Deadlift', 'Step-Up', 'Wall Sit', 'Hamstring Curl'],
            'Sat' => ['Plank', 'Side Plank', 'Bicycle Crunch', 'Dead Bug', 'Russian Twist', 'Mountain Climber', 'Jumping Jack', 'High Knees'],
        ];
        $dayTitles = ['Mon' => 'Monday: Upper Body', 'Wed' => 'Wednesday: Lower Body', 'Sat' => 'Saturday: Core and Cardio'];
        $suggestionLines = [];
        foreach ($exerciseNamesByDay as $day => $names) {
            $suggestionLines[] = '**'.$dayTitles[$day].'**';
            foreach ($names as $index => $name) {
                $suggestionLines[] = ($index + 1).'. '.$name.': 3 sets of 8-12 reps, 60 seconds rest';
            }
        }
        $suggestionLines[] = '**Tuesday: Rest**';
        $suggestionLines[] = '**Thursday: Rest**';
        $suggestionLines[] = '**Friday: Rest**';
        $suggestionLines[] = '**Sunday: Rest**';
        $suggestion = implode("\n", $suggestionLines);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/ai/suggestions/workout/save', [
                'question' => 'Suggest a 3-day workout for strength.',
                'suggestion' => $suggestion,
            ])
            ->assertCreated()
            ->assertJsonPath('workout.plan_name', 'AIVO 3-Day Build muscle Plan')
            ->assertJsonPath('workout.goal', 'Build muscle')
            ->assertJsonCount(23, 'workout.exercises')
            ->assertJsonPath('workout.exercises.0.exercise_id', $pushUpId)
            ->assertJsonPath('workout.exercises.0.day', 'Mon');

        $this->assertDatabaseCount('workouts', 1);
        $this->assertSame(
            ['Mon' => 7, 'Sat' => 8, 'Wed' => 8],
            DB::table('workout_exercises')
                ->selectRaw('day, COUNT(*) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('total', 'day')
                ->map(fn ($total) => (int) $total)
                ->all()
        );
        foreach (['Tue', 'Thu', 'Fri', 'Sun'] as $restDay) {
            $this->assertDatabaseMissing('workout_exercises', ['day' => $restDay]);
        }
        $this->assertSame(
            22,
            DB::table('exercises')->where('created_by', 'AI+'.$user->user_id)->count()
        );
        $this->assertDatabaseHas('workout_exercises', [
            'exercise_id' => $pushUpId,
            'sets' => '3',
            'reps' => '8-12',
            'day' => 'Mon',
        ]);
        Http::assertNothingSent();
    }

    public function test_custom_ai_exercises_are_only_visible_to_their_owner_and_cannot_be_used_by_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $sharedExerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Shared Push-Up',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $ownerExerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Owner AI Squat',
            'created_by' => 'AI+'.$owner->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');
        $otherExerciseId = DB::table('exercises')->insertGetId([
            'name' => 'Other User AI Lunge',
            'created_by' => 'AI+'.$otherUser->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'exercise_id');

        $this->actingAs($owner)
            ->getJson('/api/exercises')
            ->assertOk()
            ->assertJsonFragment(['exercise_id' => $sharedExerciseId])
            ->assertJsonFragment(['exercise_id' => $ownerExerciseId])
            ->assertJsonMissing(['exercise_id' => $otherExerciseId]);

        $this->actingAs($otherUser)
            ->postJson('/api/workouts', [
                'plan_name' => 'Unauthorized custom exercise test',
                'exercises' => [['exercise_id' => $ownerExerciseId]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exercises.0.exercise_id');
    }

    public function test_workout_suggestion_is_not_saved_when_it_omits_a_requested_training_day(): void
    {
        Http::preventStrayRequests();
        $suggestionLines = [];
        foreach (['Monday', 'Wednesday'] as $dayIndex => $day) {
            $suggestionLines[] = '**'.$day.': Workout**';
            foreach (range(($dayIndex * 5) + 1, ($dayIndex + 1) * 5) as $exerciseNumber) {
                $suggestionLines[] = $exerciseNumber.'. Move '.$exerciseNumber.': 3 sets of 10 reps';
            }
        }
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/ai/suggestions/workout/save', [
                'question' => 'Create a 3-day workout.',
                'suggestion' => implode("\n", $suggestionLines),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The reply needs a weekday heading for each requested workout day and 5 to 8 listed exercises per day. Ask AIVO to format the plan by weekday and try again.');

        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('workout_exercises', 0);
        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_nutrition_chat_suggestion_is_saved_to_the_requested_meal_plan_date(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $foodId = DB::table('food')->insertGetId([
            'name' => 'Greek yogurt',
            'ingredients' => 'Milk, live cultures.',
            'calories' => 100,
            'protein' => 17,
            'carbohydrates' => 6,
            'fat' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');
        Http::fake(fn () => Http::response(['message' => json_encode([
            'meal_type' => 'Breakfast',
            'items' => [['food_id' => $foodId, 'quantity' => 2]],
        ])]));
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/ai/suggestions/nutrition/save', [
                'question' => 'Suggest a high-protein breakfast.',
                'suggestion' => 'Have Greek yogurt for breakfast.',
                'meal_date' => '2026-11-15',
            ])
            ->assertCreated()
            ->assertJsonPath('meal.meal_type', 'Breakfast')
            ->assertJsonPath('meal.meal_date', '2026-11-15')
            ->assertJsonPath('meal.items.0.food_id', $foodId)
            ->assertJsonPath('meal.items.0.quantity', 2)
            ->assertJsonPath('meal.items.0.calories', 200)
            ->assertJsonPath('meal.items.0.protein', 34);

        $this->assertDatabaseCount('meals', 1);
        $this->assertDatabaseHas('meal_items', [
            'food_id' => $foodId,
            'quantity' => 2,
            'calories' => 200,
            'protein' => 34,
        ]);
    }

    public function test_nutrition_suggestion_that_matches_a_saved_allergy_is_not_saved(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $user = User::factory()->create();
        $foodId = DB::table('food')->insertGetId([
            'name' => 'Peanut butter',
            'ingredients' => 'Peanuts, salt.',
            'calories' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');
        DB::table('food_allergies')->insert([
            'user_id' => $user->user_id,
            'allergy_name' => 'Peanuts',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(fn () => Http::response(['message' => json_encode([
            'meal_type' => 'Snack',
            'items' => [['food_id' => $foodId, 'quantity' => 1]],
        ])]));

        $this->actingAs($user)
            ->postJson('/api/ai/suggestions/nutrition/save', [
                'question' => 'Suggest a snack.',
                'suggestion' => 'Try peanut butter as a snack.',
                'meal_date' => '2026-11-15',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The suggested meal may contain one of your saved allergens. Ask AIVO for another suggestion.');

        $this->assertDatabaseCount('meals', 0);
        $this->assertDatabaseCount('meal_items', 0);
    }

    public function test_nutrition_suggestion_adds_food_to_an_existing_incomplete_meal(): void
    {
        config([
            'services.ai_gateway.url' => 'https://gateway.example/api/ai/chat',
            'services.ai_gateway.key' => 'test-key',
        ]);
        $user = User::factory()->create();
        $yogurtId = DB::table('food')->insertGetId([
            'name' => 'Greek yogurt',
            'ingredients' => 'Milk, live cultures.',
            'calories' => 100,
            'protein' => 17,
            'carbohydrates' => 6,
            'fat' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');
        $bananaId = DB::table('food')->insertGetId([
            'name' => 'Banana',
            'ingredients' => 'Banana.',
            'calories' => 105,
            'protein' => 1.3,
            'carbohydrates' => 27,
            'fat' => 0.4,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'food_id');
        $mealId = DB::table('meals')->insertGetId([
            'user_id' => $user->user_id,
            'meal_type' => 'Breakfast',
            'meal_date' => '2026-11-15',
            'notes' => 'Existing breakfast',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'meal_id');
        DB::table('meal_items')->insert([
            'meal_id' => $mealId,
            'food_id' => $bananaId,
            'quantity' => 1,
            'calories' => 105,
            'protein' => 1.3,
            'carbohydrates' => 27,
            'fat' => 0.4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake(fn () => Http::response(['message' => json_encode([
            'meal_type' => 'Breakfast',
            'items' => [['food_id' => $yogurtId, 'quantity' => 1]],
        ])]));

        $this->actingAs($user)
            ->postJson('/api/ai/suggestions/nutrition/save', [
                'question' => 'Suggest a yogurt breakfast.',
                'suggestion' => 'Have Greek yogurt for breakfast.',
                'meal_date' => '2026-11-15',
            ])
            ->assertOk()
            ->assertJsonCount(2, 'meal.items')
            ->assertJsonPath('meal.notes', 'Existing breakfast');

        $this->assertDatabaseCount('meals', 1);
        $this->assertDatabaseCount('meal_items', 2);
        $this->assertDatabaseHas('meal_items', ['meal_id' => $mealId, 'food_id' => $bananaId]);
        $this->assertDatabaseHas('meal_items', ['meal_id' => $mealId, 'food_id' => $yogurtId]);
    }
}
