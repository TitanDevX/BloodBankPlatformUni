<?php

namespace Modules\Identity\Http\Controllers;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Identity\Http\ApiError;
use Modules\Identity\Http\Requests\AssignUserRoleRequest;
use Modules\Identity\Http\Requests\StoreUserRequest;
use Modules\Identity\Http\Resources\UserResource;
use Modules\Identity\Models\Role;
use Modules\Organizations\Models\Bank;
use Modules\Organizations\Models\Hospital;

class UserController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'pageSize' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'pageToken' => ['sometimes', 'string'],
            'search' => ['sometimes', 'string', 'min:1', 'max:100'],
            'active' => ['sometimes', Rule::in(['true', 'false', '1', '0'])],
            'role' => ['sometimes', 'string', 'regex:/^roles\/[a-z_]+$/'],
        ]);

        $cursor = isset($filters['pageToken']) ? Cursor::fromEncoded($filters['pageToken']) : null;
        if (isset($filters['pageToken']) && (! $cursor || ! is_numeric($cursor->toArray()['id'] ?? null))) {
            return ApiError::make(400, 'INVALID_ARGUMENT', 'INVALID_PAGE_TOKEN', 'The page token is invalid.');
        }

        $query = User::query()->with(['role:id,code', 'bank:id,public_id', 'hospital:id,public_id']);

        if (isset($filters['search'])) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']);
            $query->where('name', 'like', '%'.$search.'%');
        }

        if (array_key_exists('active', $filters)) {
            $query->where('active', $request->boolean('active'));
        }

        if (isset($filters['role'])) {
            $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('code', Str::after($filters['role'], 'roles/')));
        }

        $page = $query->orderByDesc('id')->cursorPaginate(
            ($filters['pageSize'] ?? 25) ?: 25,
            ['*'],
            'pageToken',
            $cursor,
        );

        return response()->json([
            'users' => $page->getCollection()->map(fn (User $user) => UserResource::fromUser($user))->values(),
            'nextPageToken' => $page->nextCursor()?->encode() ?? '',
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($this->hasUnknownFields($request, ['displayName', 'email', 'initialPassword', 'role', 'bank', 'hospital', 'active'])) {
            return $this->unknownFieldError();
        }

        $scope = $this->resolveRoleAndScope($data);
        if ($scope === null) {
            return $this->invalidScopeError();
        }

        $email = Str::lower($data['email']);
        if (User::query()->where('email', $email)->exists()) {
            return $this->duplicateEmailError();
        }

        [$role, $bank, $hospital] = $scope;
        $user = new User;
        $user->name = $data['displayName'];
        $user->email = $email;
        $user->password = $data['initialPassword'];
        $user->active = $data['active'];
        $user->role()->associate($role);
        $user->bank()->associate($bank);
        $user->hospital()->associate($hospital);

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateEmailError();
        }

        return response()->json(UserResource::fromUser($user));
    }

    public function show(int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);

        return response()->json(UserResource::fromUser($user));
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $data = $request->validate([
            'displayName' => ['sometimes', 'string', 'min:1', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
        ]);

        if ($this->hasUnknownFields($request, ['displayName', 'email'])) {
            return $this->unknownFieldError();
        }

        $mask = $request->query('updateMask');
        if ($mask !== null) {
            if (! is_string($mask)) {
                return $this->invalidMaskError();
            }

            $fields = $mask === '' ? [] : explode(',', $mask);
            if (array_diff($fields, ['displayName', 'email']) || array_diff($fields, array_keys($data)) || array_diff(array_keys($data), $fields)) {
                return $this->invalidMaskError();
            }
        }

        if (isset($data['displayName'])) {
            $user->name = $data['displayName'];
        }

        if (isset($data['email'])) {
            $email = Str::lower($data['email']);
            if (User::query()->where('email', $email)->where('id', '!=', $user->id)->exists()) {
                return $this->duplicateEmailError();
            }
            $user->email = $email;
        }

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateEmailError();
        }

        return response()->json(UserResource::fromUser($user));
    }

    public function assignRole(AssignUserRoleRequest $request, int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $data = $request->validated();
        if ($this->hasUnknownFields($request, ['role', 'bank', 'hospital'])) {
            return $this->unknownFieldError();
        }

        $scope = $this->resolveRoleAndScope($data);
        if ($scope === null) {
            return $this->invalidScopeError();
        }

        [$role, $bank, $hospital] = $scope;
        $updated = DB::transaction(function () use ($user, $role, $bank, $hospital): bool {
            $adminRole = Role::query()->where('code', Role::Administrator)->lockForUpdate()->firstOrFail();
            $user->refresh();

            if ($this->isLastActiveAdministrator($user, $adminRole) && $role->id !== $adminRole->id) {
                return false;
            }

            $user->role()->associate($role);
            $user->bank()->associate($bank);
            $user->hospital()->associate($hospital);
            $user->save();

            return true;
        });

        if (! $updated) {
            return $this->lastAdministratorError();
        }

        return response()->json(UserResource::fromUser($user->refresh()));
    }

    public function deactivate(Request $request, int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $data = $request->validate(['reason' => ['required', 'string', 'min:1', 'max:500']]);
        if ($this->hasUnknownFields($request, ['reason'])) {
            return $this->unknownFieldError();
        }

        $updated = DB::transaction(function () use ($request, $user, $data): bool {
            $adminRole = Role::query()->where('code', Role::Administrator)->lockForUpdate()->firstOrFail();
            $user->refresh();

            if ($this->isLastActiveAdministrator($user, $adminRole)) {
                return false;
            }

            if ($user->active) {
                $user->active = false;
                $user->save();
                $user->tokens()->delete();
                $this->recordStatusChange($user, $request->user(), 'deactivated', $data['reason']);
            }

            return true;
        });

        if (! $updated) {
            return $this->lastAdministratorError();
        }

        return response()->json(UserResource::fromUser($user));
    }

    public function activate(Request $request, int $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        if ($request->getPayload()->all() !== []) {
            return $this->unknownFieldError();
        }

        DB::transaction(function () use ($request, $user): void {
            $user->refresh();

            if (! $user->active) {
                $user->active = true;
                $user->save();
                $this->recordStatusChange($user, $request->user(), 'activated', null);
            }
        });

        return response()->json(UserResource::fromUser($user));
    }

    /** @param array<string, mixed> $data
     * @return array{Role, ?Bank, ?Hospital}|null
     */
    private function resolveRoleAndScope(array $data): ?array
    {
        $role = Role::query()->where('code', Str::after($data['role'], 'roles/'))->first();
        if ($role === null) {
            return null;
        }

        $bankName = $data['bank'] ?? null;
        $hospitalName = $data['hospital'] ?? null;

        if ($role->scope === Role::System) {
            return $bankName === null && $hospitalName === null ? [$role, null, null] : null;
        }

        if ($role->scope === Role::Bank && $bankName !== null && $hospitalName === null) {
            $bank = Bank::query()->where('public_id', Str::after($bankName, 'banks/'))->where('active', true)->first();

            return $bank ? [$role, $bank, null] : null;
        }

        if ($role->scope === Role::Hospital && $hospitalName !== null && $bankName === null) {
            $hospital = Hospital::query()->where('public_id', Str::after($hospitalName, 'hospitals/'))->where('active', true)->first();

            return $hospital ? [$role, null, $hospital] : null;
        }

        return null;
    }

    private function isLastActiveAdministrator(User $user, Role $adminRole): bool
    {
        return $user->active
            && $user->role_id === $adminRole->id
            && User::query()->where('active', true)->where('role_id', $adminRole->id)->count() <= 1;
    }

    private function recordStatusChange(User $user, User $actor, string $action, ?string $reason): void
    {
        DB::table('user_status_events')->insert([
            'user_id' => $user->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /** @param list<string> $allowed */
    private function hasUnknownFields(Request $request, array $allowed): bool
    {
        return array_diff(array_keys($request->getPayload()->all()), $allowed) !== [];
    }

    private function invalidScopeError(): JsonResponse
    {
        return ApiError::make(400, 'INVALID_ARGUMENT', 'INVALID_ROLE_SCOPE', 'The role and organization scope do not match.');
    }

    private function duplicateEmailError(): JsonResponse
    {
        return ApiError::make(409, 'ALREADY_EXISTS', 'EMAIL_ALREADY_EXISTS', 'This email address is already in use.');
    }

    private function lastAdministratorError(): JsonResponse
    {
        return ApiError::make(400, 'FAILED_PRECONDITION', 'LAST_ADMINISTRATOR', 'The last active administrator must remain active.');
    }

    private function invalidMaskError(): JsonResponse
    {
        return ApiError::make(400, 'INVALID_ARGUMENT', 'INVALID_UPDATE_MASK', 'The update mask must match the supplied mutable fields.');
    }

    private function unknownFieldError(): JsonResponse
    {
        return ApiError::make(400, 'INVALID_ARGUMENT', 'UNKNOWN_FIELD', 'The request contains an unsupported field.');
    }
}
