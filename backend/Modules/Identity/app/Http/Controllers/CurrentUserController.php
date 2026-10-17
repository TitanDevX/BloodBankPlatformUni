<?php

namespace Modules\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Resources\UserResource;

class CurrentUserController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(UserResource::fromUser($request->user()));
    }
}
