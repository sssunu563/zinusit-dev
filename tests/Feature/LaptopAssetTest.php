<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class LaptopAssetTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_laptop_show_redirects_to_hardware_asset_detail(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/asset/item/123?type=laptop');

        $response->assertRedirect(route('asset.show', [
            'assetId' => 123,
            'type' => 'assets',
        ]));
    }

    public function test_laptop_edit_redirects_to_hardware_asset_edit(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/asset/123/edit?type=laptop');

        $response->assertRedirect(route('asset.edit', [
            'assetId' => 123,
            'type' => 'assets',
        ]));
    }

    public function test_laptop_tab_data_returns_hardware_tab_data(): void
    {
        $user = User::factory()->create();

        $mockSnipe = Mockery::mock(SnipeItService::class);
        $mockSnipe->shouldReceive('fetchRows')
            ->andReturn([]);
        $mockSnipe->shouldReceive('request')
            ->andReturn(['rows' => []]);
        $this->app->instance(SnipeItService::class, $mockSnipe);

        $response = $this->actingAs($user)->getJson('/asset/item/123/tab-data?type=laptop&tab=maintenances');

        $response->assertOk();
    }

    public function test_laptop_listing_uses_hardware_page_with_laptop_filter(): void
    {
        $user = User::factory()->create();

        $mockSnipe = Mockery::mock(SnipeItService::class);
        $mockSnipe->shouldReceive('fetchRows')
            ->andReturnUsing(function (string $endpoint): array {
                if ($endpoint === 'statuslabels') {
                    return [['id' => 1, 'name' => 'Ready']];
                }

                if ($endpoint === 'hardware') {
                    return [
                        [
                            'id' => 1,
                            'name' => 'Laptop Dell',
                            'category' => ['name' => 'Laptop'],
                            'status_label' => ['id' => 1, 'name' => 'Ready'],
                        ],
                        [
                            'id' => 2,
                            'name' => 'Desktop HP',
                            'category' => ['name' => 'Desktop'],
                            'status_label' => ['id' => 1, 'name' => 'Ready'],
                        ],
                    ];
                }

                return [];
            });
        $mockSnipe->shouldReceive('requestPool')->andReturn([]);
        $this->app->instance(SnipeItService::class, $mockSnipe);

        $response = $this->actingAs($user)->get('/asset?type=assets&category=laptop');

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Asset/List')
            ->where('activeType', 'assets')
            ->where('activeTypeLabel', 'Assets')
            ->has('assets', 1)
            ->where('assets.0.name', 'Laptop Dell'));
    }
}
