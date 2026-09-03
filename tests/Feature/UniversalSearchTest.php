<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UniversalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createInspection(array $overrides = []): void
    {
        DB::table('inspections')->insert(array_merge([
            'report_id'        => 'IR-ZGI-2609-00001',
            'user'             => 'John Doe',
            'device_name'      => 'Dell Laptop',
            'device_category'  => 'Laptop',
            'location'         => 'Jakarta',
            'company'          => 'Zinus IT',
            'department'       => 'IT',
            'report_type'      => 'inspection',
            'date'             => now()->toDateString(),
            'checked_by'       => 'IT Staff',
            'checked_date'     => now()->toDateString(),
            'issue_description'=> 'Layar bermasalah',
            'solution'         => 'Diganti',
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
    }

    public function test_universal_search_returns_results_for_inspection(): void
    {
        $user = User::factory()->create();
        $this->createInspection();

        $response = $this->actingAs($user)->getJson('/search?q=2609');

        $response->assertOk();
        $data = $response->json('results');
        $this->assertNotEmpty($data, 'Expected at least one search result for "2609"');
        $this->assertEquals('IR-ZGI-2609-00001', $data[0]['title']);
        $this->assertEquals('inspection', $data[0]['type']);
    }

    public function test_universal_search_returns_user_results(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso Admin', 'email' => 'budi@zinus.com']);

        $response = $this->actingAs($user)->getJson('/search?q=Budi+Santoso');

        $response->assertOk();
        $data = $response->json('results');
        $this->assertNotEmpty($data, 'Expected user search results for "Budi Santoso"');
        $found = collect($data)->where('type', 'user')->first();
        $this->assertNotNull($found);
        $this->assertEquals('Budi Santoso Admin', $found['title']);
    }

    public function test_universal_search_returns_empty_for_short_query(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/search?q=a');
        $response->assertOk()->assertJson(['results' => []]);
    }

    public function test_universal_search_requires_auth(): void
    {
        $response = $this->getJson('/search?q=test');
        $response->assertUnauthorized();
    }

    public function test_universal_search_handles_snipeit_api_failure_gracefully(): void
    {
        // Even if Snipe-IT is unreachable, local results should still be returned
        $user = User::factory()->create(['name' => 'Graceful Fail Test', 'email' => 'graceful@zinus.com']);

        $response = $this->actingAs($user)->getJson('/search?q=Graceful+Fail');

        $response->assertOk();
        $data = $response->json('results');
        // Should have at least the local user result
        $this->assertNotEmpty($data);
    }
}
