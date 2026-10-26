<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'jane_doe',
            'email' => 'jane@example.com',
            'phone_number' => '09171234567',
            'password' => 'secure-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.username', 'jane_doe')
            ->assertJsonPath('user.first_name', 'Jane')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('users', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'jane_doe',
            'email' => 'jane@example.com',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => 1,
            'phone_number' => '09171234567',
        ]);
    }

    public function test_registration_validates_required_fields_and_unique_values(): void
    {
        User::factory()->create([
            'username' => 'existing_user',
            'email' => 'existing@example.com',
        ]);

        $this->postJson('/api/register', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'existing_user',
            'email' => 'existing@example.com',
            'password' => 'short',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'email', 'password']);
    }

    public function test_user_can_log_in_with_email_and_password(): void
    {
        User::factory()->create([
            'email' => 'jane@example.com',
            'password' => 'secure-password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'secure-password',
        ])->assertOk()
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
