<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Database\Seeders\IdentityDatabaseSeeder;
use Modules\Identity\Models\Role;
use Modules\Organizations\Models\Bank;
use Modules\Organizations\Models\Hospital;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_administrator_can_log_in_with_seeded_credentials(): void
    {
        $this->seed(IdentityDatabaseSeeder::class);
        $this->seed(IdentityDatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@demo.com')->firstOrFail();
        $this->assertDatabaseCount('users', 1);
        $this->assertNotSame('123456', $admin->password);
        $this->assertTrue(Hash::check('123456', $admin->password));

        $this->postJson('/v1/sessions', [
            'email' => 'admin@demo.com',
            'password' => '123456',
        ])->assertOk()
            ->assertJsonPath('user.role', 'roles/administrator')
            ->assertJsonPath('user.active', true)
            ->assertJsonMissingPath('user.password');
    }

    public function test_only_administrator_can_access_user_management_and_roles(): void
    {
        $this->seed(IdentityDatabaseSeeder::class);
        $bank = Bank::factory()->create();
        $staff = User::factory()->create([
            'role_id' => Role::query()->where('code', 'bank_admin')->firstOrFail()->id,
            'bank_id' => $bank->id,
        ]);
        $target = User::factory()->create();
        $payload = $this->bankUserPayload($bank);

        $this->getJson('/v1/users')->assertStatus(401);
        $this->postJson('/v1/users', $payload)->assertStatus(401);

        $this->withToken($staff->createToken('staff')->plainTextToken);
        foreach (['/v1/roles', '/v1/users', '/v1/users/'.$target->id] as $path) {
            $this->getJson($path)->assertStatus(403)
                ->assertJsonPath('error.details.0.reason', 'INSUFFICIENT_PERMISSIONS');
        }
        $this->getJson('/v1/users/999999')->assertStatus(403);
        $this->postJson('/v1/users', $payload)->assertStatus(403);
        $this->patchJson('/v1/users/'.$target->id, ['displayName' => 'Changed'])->assertStatus(403);
        $this->postJson('/v1/users/'.$target->id.':assignRole', ['role' => 'roles/administrator'])->assertStatus(403);
        $this->postJson('/v1/users/'.$target->id.':deactivate', ['reason' => 'Test'])->assertStatus(403);
        $this->postJson('/v1/users/'.$target->id.':activate')->assertStatus(403);

        $this->assertDatabaseCount('users', 3);
    }

    public function test_administrator_can_create_bank_staff_and_new_user_can_log_in(): void
    {
        $this->authenticateAdmin();
        $bank = Bank::factory()->create();

        $response = $this->postJson('/v1/users', $this->bankUserPayload($bank));

        $response->assertOk()
            ->assertJsonPath('displayName', 'Nadia Saleh')
            ->assertJsonPath('email', 'nadia@example.org')
            ->assertJsonPath('role', 'roles/bank_inventory')
            ->assertJsonPath('bank', 'banks/'.$bank->public_id)
            ->assertJsonPath('hospital', null)
            ->assertJsonMissingPath('initialPassword');
        $user = User::query()->where('email', 'nadia@example.org')->firstOrFail();
        $this->assertTrue(Hash::check('long-demo-password', $user->password));

        Auth::forgetGuards();
        $this->postJson('/v1/sessions', [
            'email' => 'nadia@example.org',
            'password' => 'long-demo-password',
        ])->assertOk()->assertJsonPath('user.role', 'roles/bank_inventory');
    }

    public function test_creation_rejects_invalid_scope_duplicate_email_and_unexpected_fields(): void
    {
        $this->authenticateAdmin();
        $bank = Bank::factory()->create();
        $payload = $this->bankUserPayload($bank);

        $this->postJson('/v1/users', array_diff_key($payload, ['bank' => true]))
            ->assertStatus(400)->assertJsonPath('error.details.0.reason', 'INVALID_ROLE_SCOPE');
        $this->postJson('/v1/users', [...$payload, 'role_id' => 1])
            ->assertStatus(400)->assertJsonPath('error.details.0.reason', 'UNKNOWN_FIELD');
        $this->postJson('/v1/users', [...$payload, 'initialPassword' => 'short'])
            ->assertStatus(400)->assertJsonPath('error.status', 'INVALID_ARGUMENT');

        $this->postJson('/v1/users', $payload)->assertOk();
        $this->postJson('/v1/users', $payload)->assertStatus(409)
            ->assertJsonPath('error.details.0.reason', 'EMAIL_ALREADY_EXISTS');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_administrator_can_list_get_and_update_users_without_changing_protected_fields(): void
    {
        $this->authenticateAdmin();
        $target = User::factory()->create(['name' => 'Nadia Saleh', 'email' => 'nadia@example.org']);

        $this->getJson('/v1/users/'.$target->id)->assertOk()->assertJsonPath('name', 'users/'.$target->id);
        $this->getJson('/v1/users/999999')->assertStatus(404)
            ->assertJsonPath('error.details.0.reason', 'RESOURCE_NOT_FOUND');
        $this->getJson('/v1/users?search=Nadia')->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.name', 'users/'.$target->id);

        $this->patchJson('/v1/users/'.$target->id.'?updateMask=displayName,email', [
            'displayName' => 'Nadia Haddad',
            'email' => 'new-nadia@example.org',
        ])->assertOk()->assertJsonPath('displayName', 'Nadia Haddad');

        $this->patchJson('/v1/users/'.$target->id, ['active' => false])->assertStatus(400)
            ->assertJsonPath('error.details.0.reason', 'UNKNOWN_FIELD');
        $this->patchJson('/v1/users/'.$target->id.'?updateMask=email', ['displayName' => 'Wrong'])
            ->assertStatus(400)->assertJsonPath('error.details.0.reason', 'INVALID_UPDATE_MASK');

        $target->refresh();
        $this->assertTrue($target->active);
        $this->assertSame('Nadia Haddad', $target->name);
        $this->assertSame('new-nadia@example.org', $target->email);
    }

    public function test_user_list_uses_a_cursor_and_filters_active_accounts(): void
    {
        $this->authenticateAdmin();
        User::factory()->count(3)->create();
        User::factory()->inactive()->create();

        $first = $this->getJson('/v1/users?pageSize=2')->assertOk()->assertJsonCount(2, 'users');
        $token = $first->json('nextPageToken');
        $this->assertNotEmpty($token);
        $this->getJson('/v1/users?pageSize=2&pageToken='.urlencode($token))->assertOk()->assertJsonCount(2, 'users');
        $this->getJson('/v1/users?active=false')->assertOk()->assertJsonCount(1, 'users');
        $this->getJson('/v1/users?role=roles/administrator')->assertOk()->assertJsonCount(1, 'users');
        $this->getJson('/v1/users?pageToken=invalid')->assertStatus(400)
            ->assertJsonPath('error.details.0.reason', 'INVALID_PAGE_TOKEN');
    }

    public function test_role_assignment_requires_matching_organization_and_lists_seeded_roles(): void
    {
        $this->authenticateAdmin();
        $target = User::factory()->create();
        $hospital = Hospital::factory()->create();

        $this->getJson('/v1/roles')->assertOk()->assertJsonCount(8, 'roles');
        $this->postJson('/v1/users/'.$target->id.':assignRole', [
            'role' => 'roles/hospital_requester',
            'bank' => 'banks/missing',
        ])->assertStatus(400)->assertJsonPath('error.details.0.reason', 'INVALID_ROLE_SCOPE');

        $this->postJson('/v1/users/'.$target->id.':assignRole', [
            'role' => 'roles/hospital_requester',
            'hospital' => 'hospitals/'.$hospital->public_id,
        ])->assertOk()->assertJsonPath('role', 'roles/hospital_requester')
            ->assertJsonPath('hospital', 'hospitals/'.$hospital->public_id);

        $target->refresh();
        $this->assertNull($target->bank_id);
        $this->assertSame($hospital->id, $target->hospital_id);
    }

    public function test_hospital_staff_creation_rejects_inactive_organization(): void
    {
        $this->authenticateAdmin();
        $hospital = Hospital::factory()->inactive()->create();
        $payload = [
            'displayName' => 'Request coordinator',
            'email' => 'coordinator@example.org',
            'initialPassword' => 'long-demo-password',
            'role' => 'roles/hospital_requester',
            'hospital' => 'hospitals/'.$hospital->public_id,
            'active' => true,
        ];

        $this->postJson('/v1/users', $payload)->assertStatus(400)
            ->assertJsonPath('error.details.0.reason', 'INVALID_ROLE_SCOPE');

        $hospital->active = true;
        $hospital->latitude = 33.5;
        $hospital->longitude = 36.3;
        $hospital->save();

        $this->postJson('/v1/users', $payload)->assertOk()
            ->assertJsonPath('hospital', 'hospitals/'.$hospital->public_id);
    }

    public function test_deactivation_revokes_tokens_records_reason_and_requires_a_new_login_after_activation(): void
    {
        $admin = $this->authenticateAdmin();
        $target = User::factory()->create();
        $oldToken = $target->createToken('staff')->plainTextToken;

        $this->postJson('/v1/users/'.$target->id.':deactivate', ['reason' => 'Staff left the shift'])
            ->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('user_status_events', [
            'user_id' => $target->id,
            'actor_user_id' => $admin->id,
            'action' => 'deactivated',
            'reason' => 'Staff left the shift',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $target->id]);

        Auth::forgetGuards();
        $this->withToken($oldToken)->getJson('/v1/users/me')->assertStatus(401);
        Auth::forgetGuards();
        $this->withToken($admin->createToken('admin-activation')->plainTextToken)
            ->postJson('/v1/users/'.$target->id.':activate')->assertOk()->assertJsonPath('active', true);
        $this->assertDatabaseHas('user_status_events', ['user_id' => $target->id, 'action' => 'activated']);

        Auth::forgetGuards();
        $this->postJson('/v1/sessions', ['email' => $target->email, 'password' => 'password'])->assertOk();
    }

    public function test_last_active_administrator_cannot_be_deactivated_or_demoted(): void
    {
        $admin = $this->authenticateAdmin();
        $bank = Bank::factory()->create();

        $this->postJson('/v1/users/'.$admin->id.':deactivate', ['reason' => 'Demo'])
            ->assertStatus(400)->assertJsonPath('error.details.0.reason', 'LAST_ADMINISTRATOR');
        $this->postJson('/v1/users/'.$admin->id.':assignRole', [
            'role' => 'roles/bank_admin',
            'bank' => 'banks/'.$bank->public_id,
        ])->assertStatus(400)->assertJsonPath('error.details.0.reason', 'LAST_ADMINISTRATOR');

        $this->assertTrue($admin->fresh()->active);
        $this->assertSame(Role::Administrator, $admin->fresh()->role->code);
        $this->assertDatabaseCount('user_status_events', 0);
    }

    public function test_inactive_or_demoted_administrator_loses_management_access(): void
    {
        $admin = $this->authenticateAdmin();
        $second = User::factory()->create([
            'role_id' => Role::query()->where('code', Role::Administrator)->firstOrFail()->id,
        ]);
        $secondToken = $second->createToken('second-admin')->plainTextToken;
        $bank = Bank::factory()->create();

        $this->postJson('/v1/users/'.$second->id.':assignRole', [
            'role' => 'roles/bank_admin',
            'bank' => 'banks/'.$bank->public_id,
        ])->assertOk();

        Auth::forgetGuards();
        $this->withToken($secondToken)->getJson('/v1/users')->assertStatus(403)
            ->assertJsonPath('error.details.0.reason', 'INSUFFICIENT_PERMISSIONS');

        $admin->active = false;
        $admin->save();
        Auth::forgetGuards();
        $this->withToken($admin->createToken('inactive-admin')->plainTextToken)->getJson('/v1/roles')
            ->assertStatus(403)->assertJsonPath('error.details.0.reason', 'ACCOUNT_INACTIVE');
    }

    private function authenticateAdmin(): User
    {
        $this->seed(IdentityDatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@demo.com')->firstOrFail();
        $this->withToken($admin->createToken('admin-test')->plainTextToken);

        return $admin;
    }

    /** @return array<string, mixed> */
    private function bankUserPayload(Bank $bank): array
    {
        return [
            'displayName' => 'Nadia Saleh',
            'email' => 'nadia@example.org',
            'initialPassword' => 'long-demo-password',
            'role' => 'roles/bank_inventory',
            'bank' => 'banks/'.$bank->public_id,
            'active' => true,
        ];
    }
}
