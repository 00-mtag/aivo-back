<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class EmailVerificationController extends Controller
{
    public function updateEmail(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => 'A verified email address cannot be changed.'], 403);
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->user_id, 'user_id'),
            ],
        ]);

        $user->forceFill([
            'email' => trim($validated['email']),
            'email_verified_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Email address updated. Verify it to finish setup.',
            'user' => $user->fresh(),
        ]);
    }

    public function send(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' => 'This email address is already verified. No link was sent.',
                'sent' => false,
            ]);
        }

        $user->notify(new VerifyEmailAddress);

        return response()->json([
            'message' => 'A verification link has been sent to '.$user->email.'.',
            'sent' => true,
        ]);
    }

    public function verify(Request $request, int $user, string $hash)
    {
        $frontendUrl = rtrim(config('services.frontend.url'), '/').'/email-verified';

        if (! URL::hasValidSignature($request)) {
            return redirect()->away($frontendUrl.'?status=invalid');
        }

        $account = User::find($user);
        if (! $account || ! hash_equals(sha1($account->email), $hash)) {
            return redirect()->away($frontendUrl.'?status=invalid');
        }

        if ($account->email_verified_at === null) {
            $account->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->away($frontendUrl.'?status=success&user_id='.$account->user_id);
    }
}
