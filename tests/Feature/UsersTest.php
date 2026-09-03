<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SnipeItManagedUserService;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_users_page(): void
    {
        $response = $this->get('/users');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_users_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Index')
            ->where('users.0.email', $user->email));
    }

    public function test_authenticated_users_can_visit_user_create_page(): void
    {
        $user = User::factory()->create();

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('fetchRows')->with('users')->andReturn([]);
            $mock->shouldReceive('fetchRows')->with('locations')->andReturn([]);
            $mock->shouldReceive('fetchRows')->with('departments')->andReturn([]);
            $mock->shouldReceive('fetchRows')->with('companies')->andReturn([]);
        });

        $response = $this->actingAs($user)->get('/users/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Create')
            ->where('options.managers', []));
    }

    public function test_authenticated_users_can_visit_user_edit_page(): void
    {
        $actingUser = User::factory()->create();
        $managedUser = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'old.user',
            'email' => 'old@example.com',
            'snipeit_user_id' => 55,
        ]);

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('fetchRows')->with('users')->andReturn([
                [
                    'id' => 55,
                    'name' => 'Old Name',
                    'first_name' => 'Old',
                    'last_name' => 'Name',
                    'username' => 'old.user',
                    'email' => 'old@example.com',
                ],
            ]);
            $mock->shouldReceive('fetchRows')->with('locations')->andReturn([]);
            $mock->shouldReceive('fetchRows')->with('departments')->andReturn([]);
            $mock->shouldReceive('fetchRows')->with('companies')->andReturn([]);
        });

        $response = $this->actingAs($actingUser)->get('/users/' . $managedUser->id . '/edit');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Users/Edit')
            ->where('user.email', 'old@example.com')
            ->where('user.first_name', 'Old'));
    }

    public function test_authenticated_users_can_create_users_from_users_page(): void
    {
        $user = User::factory()->create();

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRecord')
                ->once()
                ->with('users', \Mockery::on(fn (array $payload) => ($payload['first_name'] ?? null) === 'Test' && ($payload['email'] ?? null) === 'operator@example.com'))
                ->andReturn([
                    'status' => 'success',
                    'payload' => ['id' => 77],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->with(77)
                ->andReturn([
                    'id' => 77,
                    'first_name' => 'Test',
                    'last_name' => 'Operator',
                    'username' => 'test.operator',
                    'email' => 'operator@example.com',
                ]);
        });

        $response = $this->actingAs($user)->post('/users', [
            'first_name' => 'Test',
            'last_name' => 'Operator',
            'username' => 'test.operator',
            'email' => 'operator@example.com',
            'phone' => '08123456789',
            'jobtitle' => 'IT Support',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $createdUser = User::query()->where('email', 'operator@example.com')->first();

        $response->assertRedirect(route('users.index'));
        $this->assertNotNull($createdUser);
        $this->assertSame('test.operator', $createdUser->username);
        $this->assertTrue(Hash::check('password', $createdUser->password));
        $this->assertNotNull($createdUser->email_verified_at);
        $this->assertSame(77, $createdUser->snipeit_user_id);
    }

    public function test_authenticated_users_can_update_users_from_users_page(): void
    {
        $actingUser = User::factory()->create();
        $managedUser = User::factory()->create([
            'name' => 'Old Name',
            'username' => 'old.user',
            'email' => 'old@example.com',
            'snipeit_user_id' => 55,
        ]);

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateRecord')
                ->once()
                ->with('users', 55, \Mockery::on(fn (array $payload) => ($payload['first_name'] ?? null) === 'Updated' && ($payload['email'] ?? null) === 'updated@example.com'))
                ->andReturn([
                    'status' => 'success',
                    'payload' => ['id' => 55],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->with(55)
                ->andReturn([
                    'id' => 55,
                    'first_name' => 'Updated',
                    'last_name' => 'User',
                    'username' => 'updated.user',
                    'email' => 'updated@example.com',
                ]);
        });

        $response = $this->actingAs($actingUser)->put('/users/' . $managedUser->id, [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'username' => 'updated.user',
            'email' => 'updated@example.com',
            'phone' => '0811111111',
            'jobtitle' => 'Supervisor',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $managedUser->refresh();

        $response->assertRedirect(route('users.index'));
        $this->assertSame('Updated User', $managedUser->name);
        $this->assertSame('updated.user', $managedUser->username);
        $this->assertSame('updated@example.com', $managedUser->email);
    }

    public function test_sync_all_users_ignores_email_collisions_for_different_users(): void
    {
        $targetUser = User::factory()->create([
            'name' => 'Yuni User',
            'username' => 'yendah-nurfatwazinus-zgi',
            'email' => 'old.yuni@example.com',
            'snipeit_user_id' => 42,
        ]);

        User::factory()->create([
            'name' => 'Other User',
            'username' => 'other.user',
            'email' => 'yuni.adm@zinus.com',
            'snipeit_user_id' => 99,
        ]);

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('requestPool')->once()->andReturn([
                'p1' => ['rows' => [[
                    'id' => 42,
                    'name' => 'Yuni User',
                    'first_name' => 'Yuni',
                    'last_name' => 'User',
                    'username' => 'yendah-nurfatwazinus-zgi',
                    'email' => 'yuni.adm@zinus.com',
                    'employee_num' => '1234',
                    'avatar' => null,
                    'location' => ['name' => 'HQ'],
                    'department' => ['name' => 'IT'],
                    'company' => ['name' => 'Zinus'],
                ]]],
                'p2' => ['rows' => []],
                'p3' => ['rows' => []],
            ]);
        });

        $service = app(SnipeItManagedUserService::class);

        $this->assertSame(1, $service->syncAllUsers());
        $targetUser->refresh();

        $this->assertSame('old.yuni@example.com', $targetUser->email);
        $this->assertSame('yendah-nurfatwazinus-zgi', $targetUser->username);
    }
}