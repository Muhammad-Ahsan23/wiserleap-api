<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Cookie-based login for the Next.js admin (Laravel Sanctum SPA auth).
 * The browser first calls GET /sanctum/csrf-cookie, then these endpoints
 * with `credentials: "include"` and the X-XSRF-TOKEN header.
 */
class AuthController extends Controller
{
    public function registrationStatus(): JsonResponse
    {
        return response()->json(['open' => $this->registrationOpen()]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        abort_unless($this->registrationOpen(), 403, 'Sign-up is closed. Ask an existing admin to create your account.');

        $user = User::create($request->validated());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            // Same message for unknown email and wrong password.
            throw ValidationException::withMessages(['email' => 'Incorrect email or password.']);
        }

        $request->session()->regenerate(); // prevents session fixation

        return UserResource::make($request->user());
    }

    public function user(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    private function registrationOpen(): bool
    {
        return config('admin.registration_enabled') || ! User::query()->exists();
    }
}
