<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ExerciseController extends Controller
{
    public function index()
    {
        $exercises = DB::table('exercises')
            ->select(
                'exercise_id',
                'name',
                'description',
                'muscle_group',
                'equipment',
                'difficulty',
                'instructions',
                'best_for'
            )
            ->orderBy('name')
            ->get();

        return response()->json(['exercises' => $exercises]);
    }
}
