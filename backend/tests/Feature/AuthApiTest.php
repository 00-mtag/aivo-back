<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCodeNotification;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mockery;
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

    public function test_forgot_password_sends_a_code_and_reset_code_is_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a verification code is on its way.');

        Notification::assertSentTo($user, PasswordResetCodeNotification::class);
        $notification = Notification::sent($user, PasswordResetCodeNotification::class)->first();
        $mail = $notification->toMail($user);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $notification->code);
        $this->assertStringContainsString($notification->code, implode(' ', $mail->introLines));
        $this->assertStringContainsString('10 minutes', implode(' ', $mail->introLines));
        $this->assertNull($mail->actionUrl);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);

        $this->travel(9)->minutes();
        $payload = [
            'email' => $user->email,
            'code' => $notification->code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];

        $this->postJson('/api/reset-password', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Your password has been reset.');

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
    }

    public function test_forgot_password_does_not_disclose_unknown_email_addresses(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a verification code is on its way.');

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_password_rejects_a_mismatched_confirmation(): void
    {
        $this->postJson('/api/reset-password', [
            'email' => 'jane@example.com',
            'code' => '123456',
            'password' => 'new-secure-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_rejects_an_expired_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/forgot-password', ['email' => $user->email]);
        $notification = Notification::sent($user, PasswordResetCodeNotification::class)->first();
        $this->travel(11)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'code' => $notification->code,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_google_sign_in_creates_a_user_with_google_name_as_username(): void
    {
        $this->mockGoogleIdentity([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/auth/google', ['credential' => 'google-id-token'])
            ->assertCreated()
            ->assertJsonPath('user.username', 'Jane Doe')
            ->assertJsonPath('user.email', 'jane@example.com')
            ->assertJsonStructure(['access_token', 'token_type']);

        $this->assertDatabaseHas('users', [
            'username' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_google_sign_in_makes_duplicate_names_unique(): void
    {
        User::factory()->create(['username' => 'Jane Doe']);
        $this->mockGoogleIdentity([
            'name' => 'Jane Doe',
            'email' => 'another-jane@example.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/auth/google', ['credential' => 'google-id-token'])
            ->assertCreated()
            ->assertJsonPath('user.username', 'Jane Doe 2');
    }

    public function test_google_sign_in_reuses_an_account_with_the_verified_email(): void
    {
        $user = User::factory()->create([
            'username' => 'jane_local',
            'email' => 'jane@example.com',
        ]);
        $this->mockGoogleIdentity([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified' => true,
        ]);

        $this->postJson('/api/auth/google', ['credential' => 'google-id-token'])
            ->assertOk()
            ->assertJsonPath('user.user_id', $user->user_id)
            ->assertJsonPath('user.username', 'jane_local');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_sign_in_rejects_unverified_email(): void
    {
        $this->mockGoogleIdentity([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'email_verified' => false,
        ]);

        $this->postJson('/api/auth/google', ['credential' => 'google-id-token'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Google sign-in could not be verified.');

        $this->assertDatabaseCount('users', 0);
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

    private function mockGoogleIdentity(array $identity): void
    {
        $verifier = Mockery::mock(GoogleIdentityVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('google-id-token')
            ->andReturn($identity);

        $this->app->instance(GoogleIdentityVerifier::class, $verifier);
    }
}
