<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_kb_route_requires_auth(): void
    {
        $response = $this->get('/kb');
        $response->assertRedirect('/login');
    }

    public function test_admin_kb_route_works_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/kb');
        $response->assertOk();
    }

    public function test_admin_kb_create_route_works_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/kb/create');
        $response->assertOk();
    }

    public function test_image_upload_requires_auth(): void
    {
        $response = $this->postJson('/kb/upload-image', []);
        $response->assertUnauthorized();
    }

    public function test_image_upload_works_for_authenticated_users(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = User::factory()->create();

        $file = \Illuminate\Http\UploadedFile::fake()->image('screenshot.png', 800, 600);

        $response = $this->actingAs($user)->postJson('/kb/upload-image', [
            'image' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['url', 'filename']);

        $path = str_replace('/storage/', '', $response->json('url'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($path);
    }
}
