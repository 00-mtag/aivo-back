<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OnboardingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_profile_fields_and_image_are_saved_for_the_authenticated_user(): void
    {
        Carbon::setTestNow('2026-11-11 12:00:00');
        $user = User::factory()->create();
        $picture = UploadedFile::fake()
            ->createWithContent('profile.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAAAAAA6fptVAAAACklEQVR4nGNgAAAAAgABSK+kcQAAAABJRU5ErkJggg=='))
            ->mimeType('image/png');

        $this->actingAs($user)
            ->post('/api/profile', [
                '_method' => 'PUT',
                'gender' => 'Female',
                'phone_number' => '09171234567',
                'birth_date' => '2004-06-15',
                'height' => '170',
                'weight' => '70.5',
                'activity_level' => 'Moderately active',
                'fitness_goal' => 'Build muscle',
                'experience_level' => 'Intermediate',
                'target_weight' => '72',
                'allergies' => ['Peanuts', 'Sesame', 'Other: Latex'],
                'health_conditions' => ['Diabetes / Pre-Diabetes', 'Other: Migraine'],
                'dietary_preferences' => 'Vegetarian',
                'profile_picture' => $picture,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('profile.user_id', $user->user_id)
            ->assertJsonPath('profile.age', 22)
            ->assertJsonPath('profile.birth_date', '2004-06-15')
            ->assertJsonPath('profile.experience_level', 'Intermediate')
            ->assertJsonPath('profile.activity_level', 'Moderately active')
            ->assertJsonPath('profile.tdee', '2320')
            ->assertJsonPath('profile.allergies.0', 'Peanuts')
            ->assertJsonPath('profile.allergies.2', 'Other: Latex')
            ->assertJsonPath('profile.health_conditions.0', 'Diabetes / Pre-Diabetes')
            ->assertJsonPath('profile.health_conditions.1', 'Other: Migraine')
            ->assertJsonPath('profile.profile_picture_mime', 'image/png')
            ->assertJsonPath('profile.profile_picture', 'data:image/png;base64,'.base64_encode(file_get_contents($picture->getRealPath())));

        $stored = DB::table('user_profiles')->where('user_id', $user->user_id)->first();
        $this->assertSame(file_get_contents($picture->getRealPath()), $stored->profile_picture);
        $this->assertFalse(array_key_exists('age', (array) $stored));
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->user_id,
            'phone_number' => '09171234567',
            'fitness_goal' => 'Build muscle',
            'target_weight' => '72',
            'dietary_preferences' => 'Vegetarian',
            'tdee' => '2320',
        ]);
        $this->assertDatabaseHas('food_allergies', [
            'user_id' => $user->user_id,
            'allergy_name' => 'Peanuts',
        ]);
        $this->assertDatabaseHas('food_allergies', [
            'user_id' => $user->user_id,
            'allergy_name' => 'Other: Latex',
        ]);
        $this->assertDatabaseHas('health_conditions', [
            'user_id' => $user->user_id,
            'condition_name' => 'Diabetes / Pre-Diabetes',
        ]);
        $this->assertDatabaseHas('health_conditions', [
            'user_id' => $user->user_id,
            'condition_name' => 'Other: Migraine',
        ]);
    }

    public function test_profile_tdee_is_recalculated_when_inputs_change_and_not_taken_from_request(): void
    {
        Carbon::setTestNow('2026-11-11 12:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile', [
                'gender' => 'Male',
                'birth_date' => '1996-11-11',
                'age' => 5,
                'height' => '180',
                'weight' => '80',
                'activity_level' => 'Moderately active',
                'tdee' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('profile.age', 30)
            ->assertJsonPath('profile.tdee', '2759');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->user_id,
            'tdee' => '2759',
            'birth_date' => '1996-11-11',
        ]);
        $this->assertFalse(array_key_exists('age', (array) DB::table('user_profiles')->where('user_id', $user->user_id)->first()));
    }

    public function test_profile_image_must_be_a_supported_image_under_five_megabytes(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/api/profile', [
                '_method' => 'PUT',
                'profile_picture' => UploadedFile::fake()->create('profile.txt', 10, 'text/plain'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_picture');
    }

    public function test_profile_can_replace_or_clear_allergy_and_health_condition_selections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->putJson('/api/profile', [
                'allergies' => ['Milk', 'Eggs'],
                'health_conditions' => ['Chronic Asthma'],
            ])
            ->assertOk()
            ->assertJsonPath('profile.allergies', ['Milk', 'Eggs'])
            ->assertJsonPath('profile.health_conditions', ['Chronic Asthma']);

        $this->putJson('/api/profile', [
            'allergies' => [],
            'health_conditions' => [],
        ])->assertOk()
            ->assertJsonPath('profile.allergies', [])
            ->assertJsonPath('profile.health_conditions', []);

        $this->assertDatabaseMissing('food_allergies', ['user_id' => $user->user_id]);
        $this->assertDatabaseMissing('health_conditions', ['user_id' => $user->user_id]);
    }
}
