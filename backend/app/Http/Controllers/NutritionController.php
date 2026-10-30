<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NutritionController extends Controller
{
    public function index(Request $request)
    {
        $meals = DB::table('meals')
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('meal_date')
            ->orderByDesc('meal_id')
            ->get()
            ->map(fn ($meal) => $this->mealWithItems($meal));

        return response()->json(['meals' => $meals]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->mealRules());
        $userId = $request->user()->user_id;

        $mealId = DB::transaction(function () use ($validated, $userId) {
            $now = now();
            $mealId = DB::table('meals')->insertGetId([
                'user_id' => $userId,
                'meal_type' => $validated['meal_type'],
                'meal_date' => $validated['meal_date'],
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'meal_id');

            $this->syncItems($mealId, $validated['items'] ?? []);

            return $mealId;
        });

        return response()->json([
            'meal' => $this->mealForUser($mealId, $userId),
        ], 201);
    }

    public function show(Request $request, int $mealId)
    {
        $meal = $this->mealForUser($mealId, $request->user()->user_id);

        if (! $meal) {
            return response()->json(['message' => 'Meal not found'], 404);
        }

        return response()->json(['meal' => $meal]);
    }

    public function update(Request $request, int $mealId)
    {
        $validated = $request->validate($this->mealRules(true));
        $userId = $request->user()->user_id;
        $meal = DB::table('meals')
            ->where('meal_id', $mealId)
            ->where('user_id', $userId)
            ->first();

        if (! $meal) {
            return response()->json(['message' => 'Meal not found'], 404);
        }

        DB::transaction(function () use ($validated, $mealId, $userId) {
            $attributes = array_intersect_key($validated, array_flip([
                'meal_type',
                'meal_date',
                'notes',
            ]));

            if ($attributes !== []) {
                $attributes['updated_at'] = now();
                DB::table('meals')
                    ->where('meal_id', $mealId)
                    ->where('user_id', $userId)
                    ->update($attributes);
            }

            if (array_key_exists('items', $validated)) {
                DB::table('meal_items')->where('meal_id', $mealId)->delete();
                $this->syncItems($mealId, $validated['items']);
            }
        });

        return response()->json([
            'meal' => $this->mealForUser($mealId, $userId),
        ]);
    }

    public function goalIndex(Request $request)
    {
        $goals = DB::table('nutrition_goal')
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('start_date')
            ->orderByDesc('goal_id')
            ->get();

        return response()->json(['nutrition_goals' => $goals]);
    }

    public function storeGoal(Request $request)
    {
        $validated = $request->validate($this->goalRules());
        $userId = $request->user()->user_id;
        $now = now();
        $goalId = DB::table('nutrition_goal')->insertGetId(array_merge($validated, [
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), 'goal_id');

        return response()->json([
            'nutrition_goal' => DB::table('nutrition_goal')
                ->where('goal_id', $goalId)
                ->where('user_id', $userId)
                ->first(),
        ], 201);
    }

    public function showGoal(Request $request, int $goalId)
    {
        $goal = DB::table('nutrition_goal')
            ->where('goal_id', $goalId)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (! $goal) {
            return response()->json(['message' => 'Nutrition goal not found'], 404);
        }

        return response()->json(['nutrition_goal' => $goal]);
    }

    public function updateGoal(Request $request, int $goalId)
    {
        $validated = $request->validate($this->goalRules(true));
        $userId = $request->user()->user_id;
        $updated = DB::table('nutrition_goal')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->update(array_merge($validated, ['updated_at' => now()]));

        if ($updated === 0 && ! DB::table('nutrition_goal')
            ->where('goal_id', $goalId)
            ->where('user_id', $userId)
            ->exists()) {
            return response()->json(['message' => 'Nutrition goal not found'], 404);
        }

        return response()->json([
            'nutrition_goal' => DB::table('nutrition_goal')
                ->where('goal_id', $goalId)
                ->where('user_id', $userId)
                ->first(),
        ]);
    }

    private function mealRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'meal_type' => "$required|string|max:255",
            'meal_date' => "$required|date",
            'notes' => 'sometimes|nullable|string|max:255',
            'items' => 'sometimes|array',
            'items.*' => 'array:food_id,quantity,calories,protein,carbohydrates,fat,notes',
            'items.*.food_id' => 'required|integer|exists:food,food_id',
            'items.*.quantity' => 'sometimes|nullable|integer|min:0',
            'items.*.calories' => 'sometimes|nullable|numeric|min:0',
            'items.*.protein' => 'sometimes|nullable|numeric|min:0',
            'items.*.carbohydrates' => 'sometimes|nullable|numeric|min:0',
            'items.*.fat' => 'sometimes|nullable|numeric|min:0',
            'items.*.notes' => 'sometimes|nullable|string|max:255',
        ];
    }

    private function syncItems(int $mealId, array $items): void
    {
        foreach ($items as $item) {
            DB::table('meal_items')->insert(array_merge($item, [
                'meal_id' => $mealId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function mealForUser(int $mealId, int $userId): ?object
    {
        $meal = DB::table('meals')
            ->where('meal_id', $mealId)
            ->where('user_id', $userId)
            ->first();

        return $meal ? $this->mealWithItems($meal) : null;
    }

    private function mealWithItems(object $meal): object
    {
        $meal->items = DB::table('meal_items')
            ->join('food', 'meal_items.food_id', '=', 'food.food_id')
            ->where('meal_items.meal_id', $meal->meal_id)
            ->select('meal_items.*', 'food.name as food_name')
            ->orderBy('meal_items.meal_item_id')
            ->get();

        return $meal;
    }

    private function goalRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'daily_calories' => 'sometimes|nullable|numeric|min:0',
            'daily_protein' => 'sometimes|nullable|numeric|min:0',
            'daily_carbohydrates' => 'sometimes|nullable|numeric|min:0',
            'daily_fat' => 'sometimes|nullable|numeric|min:0',
            'start_date' => "$required|date",
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
        ];
    }
}
