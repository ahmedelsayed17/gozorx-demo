<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required','string','max:160'],
            'email' => ['required','email','max:255','unique:users,email'],
            'phone' => ['required','string','max:30','unique:users,phone'],
            'password' => ['required','string','min:10','confirmed'],
            'governorate' => ['nullable','string','max:100'],
            'city' => ['nullable','string','max:100'],
        ]);

        $user = User::create([
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => 'buyer',
            'governorate' => $data['governorate'] ?? null,
            'city' => $data['city'] ?? null,
        ]);

        return response()->json([
            'message' => 'Account created.',
            'user' => $user,
            'token' => $user->createToken('gozorx-client')->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password) || !$user->is_active) {
            throw ValidationException::withMessages(['email' => ['بيانات الدخول غير صحيحة أو الحساب غير نشط.']]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('gozorx-client')->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }
}
