<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRecord')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'payload' => ['id' => 88],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->with(88)
                ->andReturn([
                    'id' => 88,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'test.user',
                    'email' => 'test@example.com',
                ]);
        });

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Test',
                'last_name' => 'User',
                'username' => 'test.user',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test.user', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(88, $user->snipeit_user_id);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create([
            'snipeit_user_id' => 99,
        ]);

        $this->mock(SnipeItService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('updateRecord')
                ->once()
                ->with('users', 99, \Mockery::type('array'))
                ->andReturn([
                    'status' => 'success',
                    'payload' => ['id' => 99],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->with(99)
                ->andReturn([
                    'id' => 99,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'test.user',
                    'email' => $user->email,
                ]);
        });

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'first_name' => 'Test',
                'last_name' => 'User',
                'username' => 'test.user',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
