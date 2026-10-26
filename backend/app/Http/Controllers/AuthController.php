<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validatedData = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|min:3|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone_number' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'first_name' => $validatedData['first_name'],
            'last_name' => $validatedData['last_name'],
            'username' => $validatedData['username'],
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
        ]);

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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
