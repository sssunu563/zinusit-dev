<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_are_always_redirected_to_dashboard_after_login(): void
    {
        $user = User::factory()->create();

        $this->get('/auth-logs');

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_using_snipeit_username_and_sync_local_profile()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'jane@example.com',
            'username' => null,
        ]);

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('fetchRows')
                ->atLeast()->once()
                ->with('users', ['search' => 'jane.smith'], 100)
                ->andReturn([
                    [
                        'id' => 77,
                        'username' => 'jane.smith',
                        'email' => 'jane@example.com',
                        'first_name' => 'Jane',
                        'last_name' => 'Smith',
                        'name' => 'Jane Smith',
                    ],
                ]);
        });

        $response = $this->post(route('login.store'), [
            'email' => 'jane.smith',
            'password' => 'password',
        ]);

        $user->refresh();

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame('Jane Smith', $user->name);
        $this->assertSame('jane.smith', $user->username);
        $this->assertSame(77, $user->snipeit_user_id);

        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'status' => 'success',
            'identifier' => 'jane.smith',
        ]);
    }

    public function test_users_can_authenticate_locally_when_snipeit_lookup_fails()
    {
        $user = User::factory()->create();

        $this->mock(SnipeItService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('fetchRows')
                ->atLeast()->once()
                ->with('users', ['search' => $user->email], 100)
                ->andThrow(new \RuntimeException('Snipe-IT unavailable'));
        });

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event' => 'user_sync',
            'status' => 'failed',
            'identifier' => $user->email,
        ]);
        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'status' => 'success',
            'identifier' => $user->email,
        ]);
    }

    public function test_failed_login_attempts_are_logged_locally()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event' => 'login',
            'status' => 'failed',
            'identifier' => $user->email,
        ]);
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('auth_logs', [
            'user_id' => $user->id,
            'event' => 'logout',
            'status' => 'success',
            'identifier' => $user->email,
        ]);
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
