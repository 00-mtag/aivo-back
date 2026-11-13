<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\GoogleIdentityVerifier;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|min:3|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone_number' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
        //    'first_name' => $validatedData['first_name'],
        //    'last_name' => $validatedData['last_name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
        ]);

        if (! empty($validatedData['first_name'])) {
            UserProfile::create([
                'user_id' => $user->user_id,
                'first_name' => $validatedData['first_name'],
            ]);
        }
        if (! empty($validatedData['last_name'])) {
            UserProfile::create([
                'user_id' => $user->user_id,
                'last_name' => $validatedData['last_name'],
            ]);
        }
        if (! empty($validatedData['phone_number'])) {
            UserProfile::create([
                'user_id' => $user->user_id,
                'phone_number' => $validatedData['phone_number'],
            ]);
        }

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validatedData)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = User::where('email', $validatedData['email'])->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = User::where('email', $validatedData['email'])->first();

        if ($user) {
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->first();

            if (
                ! $resetRecord
                || ! $resetRecord->created_at
                || Carbon::parse($resetRecord->created_at)->addSeconds(config('auth.passwords.users.throttle'))->isPast()
            ) {
                $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                DB::table('password_reset_tokens')->updateOrInsert(
                    ['email' => $user->email],
                    ['token' => Hash::make($code), 'created_at' => now()]
                );

                $user->sendPasswordResetNotification($code);
            }
        }

        return response()->json([
            'message' => 'If an account exists for that email, a verification code is on its way.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validatedData = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $rateLimitKey = 'password-reset:'.Str::lower($validatedData['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'message' => 'Too many verification attempts. Request a new code and try again later.',
            ], 429);
        }

        $reset = DB::transaction(function () use ($validatedData, $rateLimitKey): bool {
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $validatedData['email'])
                ->lockForUpdate()
                ->first();

            if (
                ! $resetRecord
                || ! $resetRecord->created_at
                || Carbon::parse($resetRecord->created_at)->addMinutes(config('auth.passwords.users.expire'))->isPast()
                || ! Hash::check($validatedData['code'], $resetRecord->token)
            ) {
                RateLimiter::hit($rateLimitKey, config('auth.passwords.users.expire') * 60);

                return false;
            }

            $user = User::where('email', $validatedData['email'])->first();
            if (! $user) {
                RateLimiter::hit($rateLimitKey, config('auth.passwords.users.expire') * 60);

                return false;
            }

            $user->forceFill([
                'password' => Hash::make($validatedData['password']),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            RateLimiter::clear($rateLimitKey);
            event(new PasswordReset($user));

            return true;
        });

        if (! $reset) {
            return response()->json([
                'message' => 'This verification code is invalid or expired.',
                'errors' => ['code' => ['This verification code is invalid or expired.']],
            ], 422);
        }

        return response()->json(['message' => 'Your password has been reset.']);
    }

    public function google(Request $request, GoogleIdentityVerifier $googleIdentityVerifier)
    {
        $validatedData = $request->validate([
            'credential' => 'required|string|max:16384',
        ]);

        $identity = $googleIdentityVerifier->verify($validatedData['credential']);

        if (! is_array($identity)) {
            return response()->json(['message' => 'Google sign-in could not be verified.'], 401);
        }

        $name = $identity['name'] ?? null;
        $email = $identity['email'] ?? null;

        if (
            ! is_string($name)
            || trim($name) === ''
            || Str::length($name) > 255
            || ! is_string($email)
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($identity['email_verified'] ?? false) !== true
        ) {
            return response()->json(['message' => 'Google sign-in could not be verified.'], 401);
        }

        $email = trim($email);
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
        $created = $user === null;

        if ($created) {
            $user = User::create([
                'username' => $this->availableGoogleUsername(trim($name)),
                'email' => $email,
                'password' => Str::random(64),
                'email_verified_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Google sign-in successful',
            'user' => $user,
            'access_token' => $user->createToken('auth_token')->plainTextToken,
            'token_type' => 'Bearer',
        ], $created ? 201 : 200);
    }

    private function availableGoogleUsername(string $name): string
    {
        $username = $name;
        $suffix = 2;

        while (User::where('username', $username)->exists()) {
            $ending = ' '.$suffix++;
            $username = Str::substr($name, 0, 255 - Str::length($ending)).$ending;
        }

        return $username;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
