<?php

namespace Modules\Identity\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Http\ApiError;
use Modules\Identity\Http\Resources\UserResource;
use Symfony\Component\HttpFoundation\Response;

class SessionController
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'deviceDisplayName' => ['sometimes', 'string', 'min:1', 'max:100'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password) || ! $user->active) {
            return ApiError::make(401, 'UNAUTHENTICATED', 'INVALID_CREDENTIALS', 'Invalid email or password.');
        }

        $expireTime = now()->addMinutes(config('identity.token_lifetime_minutes'));
        $token = $user->createToken($credentials['deviceDisplayName'] ?? 'API session', ['*'], $expireTime);

        return response()->json([
            'name' => 'sessions/'.$token->accessToken->getKey(),
            'user' => UserResource::fromUser($user),
            'accessToken' => $token->plainTextToken,
            'tokenType' => 'Bearer',
            'expireTime' => $expireTime->toIso8601String(),
        ]);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response('', 200);
    }
}
