<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $records = DB::table('progress_records')
            ->where('user_id', $request->user()->user_id)
            ->orderByDesc('record_date')
            ->orderByDesc('progress_id')
            ->get();

        return response()->json(['progress_records' => $records]);
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
