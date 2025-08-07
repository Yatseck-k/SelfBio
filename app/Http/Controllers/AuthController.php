<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Create API token for dashboard
            $token = $request->user()->createToken('dashboard')->plainTextToken;

            return redirect()->intended('/home');
        }

        throw ValidationException::withMessages([
            'email' => ['Неверные учетные данные.'],
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}