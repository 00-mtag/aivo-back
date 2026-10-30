<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    private const PROFILE_FIELDS = [
        'age',
        'gender',
        'address',
        'phone_number',
        'height',
        'weight',
        'activity_level',
        'fitness_goal',
        'target_weight',
        'profile_picture',
        'allergies',
        'medical_conditions',
        'dietary_preferences',
    ];

    public function show(Request $request)
    {
        $profile = DB::table('user_profiles')
            ->where('user_id', $request->user()->user_id)
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'age' => 'sometimes|nullable|integer|min:0',
            'gender' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'phone_number' => 'sometimes|nullable|string|max:255',
            'height' => 'sometimes|nullable|string|max:255',
            'weight' => 'sometimes|nullable|string|max:255',
            'activity_level' => 'sometimes|nullable|string|max:255',
            'fitness_goal' => 'sometimes|nullable|string|max:255',
            'target_weight' => 'sometimes|nullable|string|max:255',
            'profile_picture' => 'sometimes|nullable|string|max:255',
            'allergies' => 'sometimes|nullable|string',
            'medical_conditions' => 'sometimes|nullable|string',
            'dietary_preferences' => 'sometimes|nullable|string',
        ]);

        $userId = $request->user()->user_id;
        $profile = DB::table('user_profiles')->where('user_id', $userId)->first();

        if ($profile) {
            if ($validated !== []) {
                DB::table('user_profiles')
                    ->where('user_id', $userId)
                    ->update(array_intersect_key($validated, array_flip(self::PROFILE_FIELDS)) + [
                        'updated_at' => now(),
                    ]);
            }
        } else {
            DB::table('user_profiles')->insert(array_merge($validated, [
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => DB::table('user_profiles')->where('user_id', $userId)->first(),
        ]);
    }
}
