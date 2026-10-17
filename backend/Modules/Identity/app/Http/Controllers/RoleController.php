<?php

namespace Modules\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Identity\Models\Role;

class RoleController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()->orderBy('code')->get()->map(fn (Role $role): array => [
                'name' => 'roles/'.$role->code,
                'code' => $role->code,
                'displayName' => $role->display_name,
                'scope' => $role->scope,
            ]),
            'nextPageToken' => '',
        ]);
    }
}
