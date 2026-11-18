<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_link_uses_the_configured_app_url(): void
    {
        $user = User::factory()->unverified()->create();
        config(['app.url' => 'https://backend.example.test']);

        $mail = (new VerifyEmailAddress)->toMail($user);

        $this->assertStringStartsWith('https://backend.example.test/email/verify/', $mail->actionUrl);
    }

    public function test_unverified_users_can_correct_their_email_and_request_a_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->patchJson('/api/account/email', ['email' => 'corrected@example.com'])
            ->assertOk()
            ->assertJsonPath('user.email', 'corrected@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        $user = $user->fresh();
        $this->actingAs($user)
            ->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'A verification link has been sent to corrected@example.com.');

        Notification::assertSentTo($user, VerifyEmailAddress::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertSame('Verify email address', $mail->actionText);
            $this->assertStringContainsString('signature=', $mail->actionUrl);

            return true;
        });
    }

    public function test_signed_verification_link_marks_the_user_email_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'email.verify',
            now()->addHour(),
            ['user' => $user->user_id, 'hash' => sha1($user->email)]
        );

        $this->get($url)
            ->assertRedirect(config('services.frontend.url').'/email-verified?status=success&user_id='.$user->user_id);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_old_verification_link_cannot_verify_an_updated_email(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'email.verify',
            now()->addHour(),
            ['user' => $user->user_id, 'hash' => sha1($user->email)]
        );
        $user->forceFill(['email' => 'corrected@example.com'])->save();

        $this->get($url)
            ->assertRedirect(config('services.frontend.url').'/email-verified?status=invalid');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verified_users_cannot_change_their_email_or_request_another_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/api/account/email', ['email' => 'changed@example.com'])
            ->assertForbidden();
        $this->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('sent', false);

        Notification::assertNothingSent();
    }
}
