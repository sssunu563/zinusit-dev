<?php

namespace Tests\Feature\Auth;

use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $this->mock(SnipeItService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createRecord')
                ->once()
                ->andReturn([
                    'status' => 'success',
                    'payload' => ['id' => 123],
                ]);

            $mock->shouldReceive('getUser')
                ->once()
                ->with(123)
                ->andReturn([
                    'id' => 123,
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'username' => 'test@example.com',
                    'email' => 'test@example.com',
                ]);
        });

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
