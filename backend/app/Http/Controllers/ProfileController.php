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
        'birth_date',
        'height',
        'weight',
        'activity_level',
        'fitness_goal',
        'experience_level',
        'target_weight',
        'profile_picture',
        'profile_picture_mime',
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

        return response()->json(['profile' => $this->presentProfile($profile, $request->user()->user_id)]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'age' => 'sometimes|nullable|integer|min:0',
            'gender' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'phone_number' => 'sometimes|nullable|string|max:255',
            'birth_date' => 'sometimes|nullable|date|before_or_equal:today',
            'height' => 'sometimes|nullable|string|max:255',
            'weight' => 'sometimes|nullable|string|max:255',
            'activity_level' => 'sometimes|nullable|string|max:255',
            'fitness_goal' => 'sometimes|nullable|string|max:255',
            'experience_level' => 'sometimes|nullable|string|max:255',
            'target_weight' => 'sometimes|nullable|string|max:255',
            'profile_picture' => 'sometimes|nullable|image|mimes:jpeg,png,webp|max:5120',
            'allergies' => 'sometimes|array',
            'allergies.*' => 'required|string|max:255|distinct',
            'health_conditions' => 'sometimes|array',
            'health_conditions.*' => 'required|string|max:255|distinct',
            'allergies_present' => 'sometimes|boolean',
            'health_conditions_present' => 'sometimes|boolean',
            'dietary_preferences' => 'sometimes|nullable|string',
        ]);

        $userId = $request->user()->user_id;
        $profile = DB::table('user_profiles')->where('user_id', $userId)->first();
        $picture = $request->file('profile_picture');
        unset($validated['profile_picture']);
        $allergies = array_key_exists('allergies', $validated) ? $validated['allergies'] : null;
        $healthConditions = array_key_exists('health_conditions', $validated) ? $validated['health_conditions'] : null;
        if (($validated['allergies_present'] ?? false) && $allergies === null) {
            $allergies = [];
        }
        if (($validated['health_conditions_present'] ?? false) && $healthConditions === null) {
            $healthConditions = [];
        }
        unset($validated['allergies'], $validated['health_conditions'], $validated['allergies_present'], $validated['health_conditions_present']);
        $attributes = array_intersect_key($validated, array_flip(self::PROFILE_FIELDS));
        if ($picture) {
            $attributes['profile_picture'] = file_get_contents($picture->getRealPath());
            $attributes['profile_picture_mime'] = $picture->getMimeType();
        }

        DB::transaction(function () use ($profile, $userId, $attributes, $allergies, $healthConditions) {
            if ($profile) {
                if ($attributes !== []) {
                    DB::table('user_profiles')
                        ->where('user_id', $userId)
                        ->update($attributes + [
                            'updated_at' => now(),
                        ]);
                }
            } else {
                DB::table('user_profiles')->insert(array_merge($attributes, [
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            if ($allergies !== null) {
                DB::table('food_allergies')->where('user_id', $userId)->delete();
                if ($allergies !== []) {
                    DB::table('food_allergies')->insert(array_map(
                        fn ($name) => ['user_id' => $userId, 'allergy_name' => $name, 'created_at' => now(), 'updated_at' => now()],
                        $allergies
                    ));
                }
            }

            if ($healthConditions !== null) {
                DB::table('health_conditions')->where('user_id', $userId)->delete();
                if ($healthConditions !== []) {
                    DB::table('health_conditions')->insert(array_map(
                        fn ($name) => ['user_id' => $userId, 'condition_name' => $name, 'created_at' => now(), 'updated_at' => now()],
                        $healthConditions
                    ));
                }
            }
        });

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $this->presentProfile(DB::table('user_profiles')->where('user_id', $userId)->first(), $userId),
        ]);
    }

    private function presentProfile(?object $profile, int $userId): ?object
    {
        if (! $profile) {
            return null;
        }

        if ($profile->profile_picture !== null) {
            $profile->profile_picture = 'data:'.$profile->profile_picture_mime.';base64,'.base64_encode($profile->profile_picture);
        }

        $profile->allergies = DB::table('food_allergies')
            ->where('user_id', $userId)
            ->orderBy('allergy_id')
            ->pluck('allergy_name');
        $profile->health_conditions = DB::table('health_conditions')
            ->where('user_id', $userId)
            ->orderBy('condition_id')
            ->pluck('condition_name');

        return $profile;
    }
}
