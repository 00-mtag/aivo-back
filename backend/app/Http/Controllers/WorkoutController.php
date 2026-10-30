<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutController extends Controller
{
    public function index(Request $request)
    {
        $workouts = DB::table('workouts')
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('workout_id')
            ->get()
            ->map(fn ($workout) => $this->workoutWithExercises($workout));

        return response()->json(['workouts' => $workouts]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->workoutRules());
        $userId = $request->user()->user_id;
        $workoutId = DB::transaction(function () use ($validated, $userId) {
            $now = now();
            $workoutId = DB::table('workouts')->insertGetId([
                'user_id' => $userId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'duration' => $validated['duration'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'workout_id');

            $this->syncExercises($workoutId, $validated['exercises'] ?? []);

            return $workoutId;
        });

        return response()->json([
            'workout' => $this->workoutForUser($workoutId, $userId),
        ], 201);
    }

    public function show(Request $request, int $workoutId)
    {
        $workout = $this->workoutForUser($workoutId, $request->user()->user_id);

        if (! $workout) {
            return response()->json(['message' => 'Workout not found'], 404);
        }

        return response()->json(['workout' => $workout]);
    }

    public function update(Request $request, int $workoutId)
    {
        $validated = $request->validate($this->workoutRules(true));
        $userId = $request->user()->user_id;

        if (! DB::table('workouts')
            ->where('workout_id', $workoutId)
            ->where('user_id', $userId)
            ->exists()) {
            return response()->json(['message' => 'Workout not found'], 404);
        }

        DB::transaction(function () use ($validated, $workoutId, $userId) {
            $attributes = array_intersect_key($validated, array_flip([
                'name',
                'description',
                'duration',
                'notes',
            ]));

            if ($attributes !== []) {
                $attributes['updated_at'] = now();
                DB::table('workouts')
                    ->where('workout_id', $workoutId)
                    ->where('user_id', $userId)
                    ->update($attributes);
            }

            if (array_key_exists('exercises', $validated)) {
                DB::table('workout_exercises')->where('workout_id', $workoutId)->delete();
                $this->syncExercises($workoutId, $validated['exercises']);
            }
        });

        return response()->json([
            'workout' => $this->workoutForUser($workoutId, $userId),
        ]);
    }

    private function workoutRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'name' => "$required|string|max:255",
            'description' => 'sometimes|nullable|string|max:255',
            'duration' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:255',
            'exercises' => 'sometimes|array',
            'exercises.*' => 'array:exercise_id,sets,reps,weight,rest_seconds',
            'exercises.*.exercise_id' => 'required|integer|exists:exercises,exercise_id|distinct',
            'exercises.*.sets' => 'sometimes|nullable|string|max:255',
            'exercises.*.reps' => 'sometimes|nullable|string|max:255',
            'exercises.*.weight' => 'sometimes|nullable|string|max:255',
            'exercises.*.rest_seconds' => 'sometimes|nullable|string|max:255',
        ];
    }

    private function syncExercises(int $workoutId, array $exercises): void
    {
        foreach ($exercises as $exercise) {
            DB::table('workout_exercises')->insert(array_merge($exercise, [
                'workout_id' => $workoutId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    private function workoutForUser(int $workoutId, int $userId): ?object
    {
        $workout = DB::table('workouts')
            ->where('workout_id', $workoutId)
            ->where('user_id', $userId)
            ->first();

        return $workout ? $this->workoutWithExercises($workout) : null;
    }

    private function workoutWithExercises(object $workout): object
    {
        $workout->exercises = DB::table('workout_exercises')
            ->join('exercises', 'workout_exercises.exercise_id', '=', 'exercises.exercise_id')
            ->where('workout_exercises.workout_id', $workout->workout_id)
            ->select(
                'workout_exercises.*',
                'exercises.name',
                'exercises.description',
                'exercises.muscle_group',
                'exercises.equipment',
                'exercises.difficulty',
                'exercises.instructions'
            )
            ->orderBy('workout_exercises.workout_exercise_id')
            ->get();

        return $workout;
    }
}
