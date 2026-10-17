<?php

namespace Modules\Identity\Http\Resources;

use App\Models\User;

final class UserResource
{
    /** @return array<string, mixed> */
    public static function fromUser(User $user): array
    {
        $user->loadMissing(['role:id,code', 'bank:id,public_id', 'hospital:id,public_id']);

        return [
            'name' => 'users/'.$user->getKey(),
            'displayName' => $user->name,
            'email' => $user->email,
            'role' => $user->role ? 'roles/'.$user->role->code : null,
            'bank' => $user->bank ? 'banks/'.$user->bank->public_id : null,
            'hospital' => $user->hospital ? 'hospitals/'.$user->hospital->public_id : null,
            'active' => $user->active,
            'createTime' => $user->created_at?->toIso8601String(),
            'updateTime' => $user->updated_at?->toIso8601String(),
        ];
    }
}
