<?php

namespace Modules\Identity\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in_and_read_current_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Nadia Saleh',
            'email' => 'nadia@example.org',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->postJson('/v1/sessions', [
            'email' => 'nadia@example.org',
            'password' => 'secret-password',
            'deviceDisplayName' => 'Ward tablet',
        ]);

        $response->assertOk()->assertJsonPath('user.name', 'users/'.$user->id)
            ->assertJsonPath('user.displayName', 'Nadia Saleh')
            ->assertJsonPath('user.active', true)
            ->assertJsonPath('tokenType', 'Bearer');

        $token = $response->json('accessToken');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($response->json('expireTime'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Ward tablet',
            'token' => hash('sha256', explode('|', $token, 2)[1]),
        ]);

        $this->withToken($token)->getJson('/v1/users/me')->assertOk()
            ->assertJsonPath('email', 'nadia@example.org')
            ->assertJsonMissingPath('password');
    }

    public function test_wrong_password_unknown_email_and_inactive_account_share_a_generic_401(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.org',
            'password' => Hash::make('secret-password'),
        ]);

        foreach ([
            ['inactive@example.org', 'secret-password'],
            ['unknown@example.org', 'secret-password'],
            ['inactive@example.org', 'wrong-password'],
        ] as [$email, $password]) {
            $this->postJson('/v1/sessions', compact('email', 'password'))
                ->assertStatus(401)
                ->assertJsonPath('error.status', 'UNAUTHENTICATED')
                ->assertJsonPath('error.details.0.reason', 'INVALID_CREDENTIALS')
                ->assertJsonPath('error.message', 'Invalid email or password.');
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_validates_required_fields_and_device_name(): void
    {
        $this->postJson('/v1/sessions', [])->assertStatus(400)
            ->assertJsonPath('error.status', 'INVALID_ARGUMENT')
            ->assertJsonPath('error.details.1.@type', 'type.googleapis.com/google.rpc.BadRequest');

        $this->postJson('/v1/sessions', [
            'email' => 'bad-email',
            'password' => 'password',
            'deviceDisplayName' => str_repeat('x', 101),
        ])->assertStatus(400)->assertJsonPath('error.details.0.reason', 'VALIDATION_FAILED');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/v1/sessions', [
                'email' => 'unknown@example.org',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/v1/sessions', [
            'email' => 'unknown@example.org',
            'password' => 'wrong-password',
        ])->assertStatus(429)->assertJsonPath('error.details.0.reason', 'LOGIN_RATE_LIMITED');
    }

    public function test_protected_routes_require_a_valid_token(): void
    {
        $this->get('/v1/users/me')->assertStatus(401)
            ->assertJsonPath('error.details.0.reason', 'AUTHENTICATION_REQUIRED');
        $this->withToken('invalid')->delete('/v1/sessions/current')->assertStatus(401)
            ->assertJsonPath('error.status', 'UNAUTHENTICATED');
    }

    public function test_deactivation_blocks_a_previously_issued_token_on_every_protected_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'], now()->addHour())->plainTextToken;

        $this->withToken($token)->getJson('/v1/users/me')->assertOk();
        $user->active = false;
        $user->save();

        $this->withToken($token)->getJson('/v1/users/me')->assertStatus(403)
            ->assertJsonPath('error.details.0.reason', 'ACCOUNT_INACTIVE');
        $this->withToken($token)->deleteJson('/v1/sessions/current')->assertStatus(403);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('expired', ['*'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/v1/users/me')->assertStatus(401);
    }

    public function test_logout_revokes_only_the_presenting_token(): void
    {
        $user = User::factory()->create();
        $first = $user->createToken('first', ['*'], now()->addHour())->plainTextToken;
        $second = $user->createToken('second', ['*'], now()->addHour())->plainTextToken;

        $response = $this->withToken($first)->deleteJson('/v1/sessions/current')->assertOk();
        $this->assertSame('', $response->getContent());
        Auth::forgetGuards();
        $this->withToken($first)->getJson('/v1/users/me')->assertStatus(401);
        Auth::forgetGuards();
        $this->withToken($second)->getJson('/v1/users/me')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
