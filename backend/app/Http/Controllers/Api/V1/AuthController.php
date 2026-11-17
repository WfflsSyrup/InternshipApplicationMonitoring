<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Requests\Auth\LoginRequest;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $token = $user->createToken('flutter-web')->plainTextToken;

        return self::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Signed in successfully.');
    }

    public function logout(): JsonResponse
    {
        $token = request()->user()?->currentAccessToken();
        $token?->delete();

        return self::success(null, 'Signed out successfully.');
    }

    public function me(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return self::success(new UserResource($user), 'Session is active.');
    }
}