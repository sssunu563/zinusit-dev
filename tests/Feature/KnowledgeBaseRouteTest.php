<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_kb_route_works(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/kb');

        $response->assertOk();
    }
}
