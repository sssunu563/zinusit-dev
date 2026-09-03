<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ToolsRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_all_tools_submenus(): void
    {
        $user = User::factory()->create();

        // 1. Label Engine
        $response = $this->actingAs($user)->get('/label-generator');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('LabelGenerator/Index'));

        // 2. Knowledge Base
        $response = $this->actingAs($user)->get('/kb');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('KnowledgeBase/Index'));

        // 3. Stock Opname / Audit
        $response = $this->actingAs($user)->get('/audit');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Audit/Index'));

        // 4. Vendors
        $response = $this->actingAs($user)->get('/vendors');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Vendors/Index'));

        // 5. Procurement
        $response = $this->actingAs($user)->get('/procurement');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Procurement/Index'));
    }
}
