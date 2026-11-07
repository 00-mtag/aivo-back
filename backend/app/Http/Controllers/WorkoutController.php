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
        $validated = $this->validateWorkout($request);
        $userId = $request->user()->user_id;
        $workoutId = DB::transaction(function () use ($validated, $userId) {
            $now = now();
            $workoutId = DB::table('workouts')->insertGetId([
                'user_id' => $userId,
                'plan_name' => $validated['plan_name'],
                'description' => $validated['description'] ?? null,
                'goal' => $validated['goal'] ?? null,
                'level' => $validated['level'] ?? null,
                'minutes_per_session' => $validated['minutes_per_session'] ?? null,
                'duration' => $validated['duration'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'active' => $validated['active'] ?? true,
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
        $validated = $this->validateWorkout($request, true);
        $userId = $request->user()->user_id;

        if (! DB::table('workouts')
            ->where('workout_id', $workoutId)
            ->where('user_id', $userId)
            ->exists()) {
            return response()->json(['message' => 'Workout not found'], 404);
        }

        DB::transaction(function () use ($validated, $workoutId, $userId) {
            $attributes = array_intersect_key($validated, array_flip([
                'plan_name',
                'description',
                'goal',
                'level',
                'minutes_per_session',
                'duration',
                'notes',
                'active',
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

    private function validateWorkout(Request $request, bool $partial = false): array
    {
        if (! $request->has('plan_name') && $request->has('name')) {
            $request->merge(['plan_name' => $request->input('name')]);
        }

        return $request->validate($this->workoutRules($partial));
    }

    private function workoutRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'plan_name' => "$required|string|max:255",
            'description' => 'sometimes|nullable|string|max:255',
            'goal' => 'sometimes|nullable|string|max:255',
            'level' => 'sometimes|nullable|string|max:255',
            'minutes_per_session' => 'sometimes|nullable|string|max:255',
            'duration' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:255',
            'exercises' => 'sometimes|array',
            'active' => 'sometimes|boolean',
            'exercises.*' => 'array:day,exercise_id,sets,reps,weight,rest_seconds,notes,muscle,sort_order',
            'exercises.*.day' => 'sometimes|nullable|string|in:Mon,Tue,Wed,Thu,Fri,Sat,Sun',
            'exercises.*.exercise_id' => 'required|integer|exists:exercises,exercise_id',
            'exercises.*.sets' => 'sometimes|nullable|string|max:255',
            'exercises.*.reps' => 'sometimes|nullable|string|max:255',
            'exercises.*.weight' => 'sometimes|nullable|string|max:255',
            'exercises.*.rest_seconds' => 'sometimes|nullable|string|max:255',
            'exercises.*.notes' => 'sometimes|nullable|string|max:255',
            'exercises.*.muscle' => 'sometimes|nullable|string|max:255',
            'exercises.*.sort_order' => 'sometimes|integer|min:0',
        ];
    }

    private function syncExercises(int $workoutId, array $exercises): void
    {
        foreach ($exercises as $sortOrder => $exercise) {
            DB::table('workout_exercises')->insert(array_merge($exercise, [
                'workout_id' => $workoutId,
                'sort_order' => $exercise['sort_order'] ?? $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function destroy(Request $request, int $workoutId)
    {
        $deleted = DB::table('workouts')
            ->where('workout_id', $workoutId)
            ->where('user_id', $request->user()->user_id)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Workout not found'], 404);
        }

        return response()->json(['message' => 'Workout deleted']);
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
        $workout->name = $workout->plan_name;
        $workout->exercises = DB::table('workout_exercises')
            ->join('exercises', 'workout_exercises.exercise_id', '=', 'exercises.exercise_id')
            ->where('workout_exercises.workout_id', $workout->workout_id)
            ->select(
                'workout_exercises.*',
                'exercises.name',
                'exercises.description',
                'exercises.muscle_group as catalog_muscle_group',
                'exercises.equipment',
                'exercises.difficulty',
                'exercises.instructions',
                'exercises.best_for'
            )
            ->orderBy('workout_exercises.sort_order')
            ->orderBy('workout_exercises.workout_exercise_id')
            ->get();

        return $workout;
    }
}
