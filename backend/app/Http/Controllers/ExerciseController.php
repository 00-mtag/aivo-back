<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExerciseController extends Controller
{
    private const GOAL_ALIASES = [
        'lose' => 'Lose weight',
        'lose weight' => 'Lose weight',
        'lost' => 'Lose weight',
        'lost weight' => 'Lose weight',
        'build' => 'Build muscle',
        'build muscle' => 'Build muscle',
        'endurance' => 'Improve endurance',
        'improve endurance' => 'Improve endurance',
        'maintain' => 'Maintain performance',
        'maintain performance' => 'Maintain performance',
        'maintain fitness' => 'Maintain performance',
    ];

    public function index(Request $request)
    {
        $query = DB::table('exercises')
            ->select(
                'exercise_id',
                'name',
                'description',
                'muscle_group',
                'equipment',
                'difficulty',
                'instructions',
                'best_for'
            );

        $requestedGoals = $request->query('best_for');

        if ($requestedGoals !== null && $requestedGoals !== '') {
            $goals = is_array($requestedGoals)
                ? $requestedGoals
                : explode(',', $requestedGoals);

            $goals = array_map(
                fn ($goal) => is_string($goal) ? mb_strtolower(trim($goal)) : $goal,
                $goals
            );

            $validated = Validator::make(
                ['best_for' => $goals],
                [
                    'best_for' => ['required', 'array', 'min:1', 'max:4'],
                    'best_for.*' => ['required', 'string', 'in:'.implode(',', array_keys(self::GOAL_ALIASES))],
                ]
            )->validate();

            $canonicalGoals = array_unique(array_map(
                fn (string $goal) => self::GOAL_ALIASES[$goal],
                $validated['best_for']
            ));

            $query->where(function ($goalQuery) use ($canonicalGoals) {
                foreach ($canonicalGoals as $goal) {
                    $goalQuery->orWhere('best_for', 'like', "%{$goal}%");
                }
            });
        }

        $exercises = $query->orderBy('name')->get();

        return response()->json(['exercises' => $exercises]);
    }
}
