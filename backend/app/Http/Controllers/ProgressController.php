<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->user_id;
        $records = DB::table('progress_records')
            ->where('user_id', $userId)
            ->orderByDesc('record_date')
            ->orderByDesc('progress_id')
            ->get();
        $workoutRecords = DB::table('progress_workout_records')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('progress_workout_id')
            ->get();
        $calorieBurnedRecords = DB::table('calorie_burned_records')
            ->join('exercises', 'calorie_burned_records.exercise_id', '=', 'exercises.exercise_id')
            ->where('calorie_burned_records.user_id', $userId)
            ->orderByDesc('calorie_burned_records.created_at')
            ->select(
                'calorie_burned_records.calorie_burned_id',
                'calorie_burned_records.workout_id',
                'calorie_burned_records.exercise_id',
                'exercises.name as exercise_name',
                'calorie_burned_records.calories_burned',
                'calorie_burned_records.created_at'
            )
            ->get();
        $calorieIntakeRecords = DB::table('calorie_intake_records')
            ->join('meals', 'calorie_intake_records.meal_id', '=', 'meals.meal_id')
            ->where('calorie_intake_records.user_id', $userId)
            ->whereNotNull('meals.completed_at')
            ->orderByDesc('calorie_intake_records.created_at')
            ->select(
                'calorie_intake_records.calorie_intake_id',
                'calorie_intake_records.meal_id',
                'calorie_intake_records.food_id',
                'calorie_intake_records.calories_intake',
                'meals.meal_date'
            )
            ->get();

        return response()->json([
            'progress_records' => $records,
            'workout_records' => $workoutRecords,
            'calorie_burned_records' => $calorieBurnedRecords,
            'calorie_intake_records' => $calorieIntakeRecords,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->recordRules());
        $userId = $request->user()->user_id;
        $now = now();
        $progressId = DB::table('progress_records')->insertGetId(array_merge($validated, [
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]), 'progress_id');

        return response()->json([
            'progress_record' => DB::table('progress_records')
                ->where('progress_id', $progressId)
                ->where('user_id', $userId)
                ->first(),
        ], 201);
    }

    public function storeWorkout(Request $request)
    {
        $validated = $request->validate([
            'workout_id' => 'required|integer|exists:workouts,workout_id',
            'workout_type' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'workout_duration' => 'required|integer|min:0',
            'exercises_done' => 'required|integer|min:0',
            'exercises_skipped' => 'required|integer|min:0',
            'body_weight_kg' => 'sometimes|numeric|min:20|max:350',
            'exercise_durations' => 'sometimes|array',
            'exercise_durations.*' => 'required|array:workout_exercise_id,duration_seconds',
            'exercise_durations.*.workout_exercise_id' => 'required|integer|distinct',
            'exercise_durations.*.duration_seconds' => 'required|integer|min:1|max:21600',
        ]);
        $userId = $request->user()->user_id;

        $workout = DB::table('workouts')
            ->where('workout_id', $validated['workout_id'])
            ->where('user_id', $userId)
            ->first();

        if (! $workout) {
            return response()->json(['message' => 'Workout not found'], 404);
        }

        $exerciseDurations = $validated['exercise_durations'] ?? [];
        $calorieRecords = [];
        if ($exerciseDurations !== []) {
            $bodyWeightKg = $this->bodyWeightKg($userId, $validated['body_weight_kg'] ?? null);
            if ($bodyWeightKg === null) {
                return response()->json([
                    'message' => 'Enter your body weight in your profile or provide it to estimate calories.',
                    'errors' => ['body_weight_kg' => ['Body weight is required to estimate calories burned.']],
                ], 422);
            }

            $workoutDay = [
                'Monday' => 'Mon',
                'Tuesday' => 'Tue',
                'Wednesday' => 'Wed',
                'Thursday' => 'Thu',
                'Friday' => 'Fri',
                'Saturday' => 'Sat',
                'Sunday' => 'Sun',
            ][$validated['workout_type'] ?? ''] ?? null;
            $exerciseIds = array_column($exerciseDurations, 'workout_exercise_id');
            $plannedExercises = DB::table('workout_exercises')
                ->join('exercises', 'workout_exercises.exercise_id', '=', 'exercises.exercise_id')
                ->where('workout_exercises.workout_id', $workout->workout_id)
                ->where('workout_exercises.day', $workoutDay)
                ->whereIn('workout_exercises.workout_exercise_id', $exerciseIds)
                ->select(
                    'workout_exercises.workout_exercise_id',
                    'workout_exercises.exercise_id',
                    'exercises.name'
                )
                ->get()
                ->keyBy('workout_exercise_id');

            if ($workoutDay === null || $plannedExercises->count() !== count($exerciseDurations)) {
                return response()->json(['message' => 'One or more completed exercises do not belong to this workout day.'], 422);
            }

            foreach ($exerciseDurations as $exerciseDuration) {
                $exercise = $plannedExercises->get($exerciseDuration['workout_exercise_id']);
                $seconds = (int) $exerciseDuration['duration_seconds'];
                $calories = round(
                    $this->metForExercise($exercise->name) * 3.5 * $bodyWeightKg * ($seconds / 60) / 200,
                    2
                );
                $calorieRecords[] = [
                    'workout_exercise_id' => (int) $exercise->workout_exercise_id,
                    'exercise_id' => (int) $exercise->exercise_id,
                    'name' => $exercise->name,
                    'duration_seconds' => $seconds,
                    'calories_burned' => $calories,
                ];
            }
        }

        [$progressWorkoutId, $savedCalorieRecords] = DB::transaction(function () use ($validated, $userId, $workout, $calorieRecords) {
            $now = now();
            $progressWorkoutId = DB::table('progress_workout_records')->insertGetId([
                'user_id' => $userId,
                'workout_id' => $workout->workout_id,
                'workout_name' => $workout->plan_name,
                'workout_type' => $validated['workout_type'] ?? null,
                'workout_duration' => (string) $validated['workout_duration'],
                'workout_intensity' => $workout->level,
                'exercises_done' => (string) $validated['exercises_done'],
                'exercises_skipped' => (string) $validated['exercises_skipped'],
                'created_at' => $now,
                'updated_at' => $now,
            ], 'progress_workout_id');

            foreach ($calorieRecords as $calorieRecord) {
                DB::table('calorie_burned_records')->insert([
                    'user_id' => $userId,
                    'workout_id' => $workout->workout_id,
                    'exercise_id' => $calorieRecord['exercise_id'],
                    'calories_burned' => (string) $calorieRecord['calories_burned'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            return [$progressWorkoutId, $calorieRecords];
        });

        return response()->json([
            'progress_workout_record' => DB::table('progress_workout_records')
                ->where('progress_workout_id', $progressWorkoutId)
                ->where('user_id', $userId)
                ->first(),
            'calorie_burned_records' => $savedCalorieRecords,
            'total_calories_burned' => round(array_sum(array_column($savedCalorieRecords, 'calories_burned')), 2),
        ], 201);
    }

    private function bodyWeightKg(int $userId, mixed $providedWeight): ?float
    {
        $weight = is_numeric($providedWeight)
            ? (float) $providedWeight
            : DB::table('user_profiles')->where('user_id', $userId)->value('weight');

        if (! is_string($weight) && ! is_numeric($weight)) {
            return null;
        }
        if (! preg_match('/\d+(?:\.\d+)?/', (string) $weight, $matches)) {
            return null;
        }

        $weightKg = (float) $matches[0];
        if (preg_match('/\b(lb|lbs|pound|pounds)\b/i', (string) $weight)) {
            $weightKg *= 0.45359237;
        }

        return $weightKg >= 20 && $weightKg <= 350 ? $weightKg : null;
    }

    private function metForExercise(string $exerciseName): float
    {
        $name = mb_strtolower($exerciseName);

        return match (true) {
            str_contains($name, 'jump rope') => 10.0,
            str_contains($name, 'burpee'),
            str_contains($name, 'high knees'),
            str_contains($name, 'jumping jack'),
            str_contains($name, 'mountain climber') => 8.0,
            str_contains($name, 'rowing machine') => 7.0,
            str_contains($name, 'stationary bike') => 6.8,
            str_contains($name, 'elliptical') => 5.0,
            str_contains($name, 'treadmill walk') => 3.5,
            str_contains($name, 'plank'),
            str_contains($name, 'wall sit'),
            str_contains($name, 'hold') => 3.0,
            default => 3.5,
        };
    }

    public function show(Request $request, int $progressId)
    {
        $record = DB::table('progress_records')
            ->where('progress_id', $progressId)
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Progress record not found'], 404);
        }

        return response()->json(['progress_record' => $record]);
    }

    public function update(Request $request, int $progressId)
    {
        $validated = $request->validate($this->recordRules(true));
        $userId = $request->user()->user_id;
        $updated = DB::table('progress_records')
            ->where('progress_id', $progressId)
            ->where('user_id', $userId)
            ->update(array_merge($validated, ['updated_at' => now()]));

        if ($updated === 0 && ! DB::table('progress_records')
            ->where('progress_id', $progressId)
            ->where('user_id', $userId)
            ->exists()) {
            return response()->json(['message' => 'Progress record not found'], 404);
        }

        return response()->json([
            'progress_record' => DB::table('progress_records')
                ->where('progress_id', $progressId)
                ->where('user_id', $userId)
                ->first(),
        ]);
    }

    private function recordRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            'record_date' => "$required|date",
            'weight' => 'sometimes|nullable|numeric|min:0',
            'body_fat' => 'sometimes|nullable|numeric|min:0',
            'muscle_mass' => 'sometimes|nullable|numeric|min:0',
            'waist_circumference' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string|max:255',
        ];
    }
}
