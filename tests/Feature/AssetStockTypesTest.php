<?php

namespace Tests\Feature;

use App\Models\AssetStockHistory;
use App\Models\User;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AssetStockTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_consumable_create_receives_all_master_options(): void
    {
        $user = User::factory()->create();
        $snipe = Mockery::mock(SnipeItService::class);
        $snipe->shouldReceive('requestPool')->once()->andReturn([
            'users_p1' => ['rows' => []],
            'users_p2' => ['rows' => []],
            'models_p1' => ['rows' => []],
            'models_p2' => ['rows' => []],
            'models_p3' => ['rows' => []],
            'locations' => ['rows' => [['id' => 31, 'name' => 'Main Warehouse']]],
            'companies' => ['rows' => [['id' => 32, 'name' => 'Acme']]],
            'manufacturers' => ['rows' => [['id' => 33, 'name' => 'Maker']]],
            'suppliers' => ['rows' => [['id' => 34, 'name' => 'Supplier']]],
            'categories_all' => ['rows' => [['id' => 35, 'name' => 'Office', 'category_type' => 'consumable']]],
            'statuslabels' => ['rows' => []],
            'fieldsets' => ['rows' => []],
        ]);
        $this->app->instance(SnipeItService::class, $snipe);

        $response = $this->actingAs($user)->get('/asset/create?type=consumable');

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Asset/Create')
            ->where('metadata.consumable.categories.0.id', 35)
            ->where('metadata.consumable.companies.0.id', 32)
            ->where('metadata.consumable.locations.0.id', 31)
            ->where('metadata.consumable.manufacturers.0.id', 33)
            ->where('metadata.consumable.suppliers.0.id', 34));
    }

    public function test_create_sends_the_correct_snipe_it_endpoint_and_payload_for_each_stock_type(): void
    {
        $user = User::factory()->create();
        $snipe = Mockery::mock(SnipeItService::class);

        $cases = [
            'license' => [
                'endpoint' => 'licenses',
                'fields' => [
                    'name' => 'Office License', 'seats' => 5, 'category_id' => 11,
                    'serial' => 'KEY-123', 'po_number' => 'PO-001',
                ],
                'payload' => ['name' => 'Office License', 'seats' => 5, 'category_id' => 11, 'product_key' => 'KEY-123'],
            ],
            'accessories' => [
                'endpoint' => 'accessories',
                'fields' => ['name' => 'Dock', 'qty' => 4, 'category_id' => 12, 'min_qty' => 2],
                'payload' => ['name' => 'Dock', 'qty' => 4, 'category_id' => 12, 'min_qty' => 2],
            ],
            'consumable' => [
                'endpoint' => 'consumables',
                'fields' => ['name' => 'Toner', 'qty' => 4, 'category_id' => 13, 'min_qty' => 2, 'item_no' => 'TN-01'],
                'payload' => ['name' => 'Toner', 'qty' => 4, 'category_id' => 13, 'min_amt' => 2, 'item_no' => 'TN-01'],
            ],
            'component' => [
                'endpoint' => 'components',
                'fields' => ['name' => 'Memory', 'qty' => 4, 'category_id' => 14, 'serial' => 'RAM-01'],
                'payload' => ['name' => 'Memory', 'qty' => 4, 'category_id' => 14, 'serial' => 'RAM-01'],
            ],
        ];

        foreach ($cases as $type => $case) {
            $expectedPayload = $case['payload'];
            $snipe->shouldReceive('createRecord')
                ->once()
                ->with($case['endpoint'], Mockery::on(function (array $payload) use ($expectedPayload): bool {
                    foreach ($expectedPayload as $key => $value) {
                        if (($payload[$key] ?? null) !== $value) {
                            return false;
                        }
                    }

                    return true;
                }))
                ->andReturn(['status' => 'success', 'payload' => ['id' => 100 + count($cases)]]);
        }

        $this->app->instance(SnipeItService::class, $snipe);

        foreach ($cases as $type => $case) {
            $response = $this->actingAs($user)->post('/asset', array_merge(
                ['type' => $type],
                $case['fields'],
            ));

            $response->assertRedirect(route('asset.index', ['type' => $type]));
        }

        $this->assertDatabaseHas('asset_stock_histories', [
            'asset_type' => 'license',
            'po_number' => 'PO-001',
        ]);
    }

    public function test_edit_updates_snipe_it_without_creating_a_new_stock_history_entry(): void
    {
        $user = User::factory()->create();
        $snipe = Mockery::mock(SnipeItService::class);

        $cases = [
            ['type' => 'license', 'endpoint' => 'licenses', 'getter' => 'getLicense', 'record' => ['id' => 201, 'name' => 'Office License', 'seats' => 5, 'category' => ['id' => 11], 'product_key' => 'KEY-OLD']],
            ['type' => 'accessories', 'endpoint' => 'accessories', 'getter' => 'getAccessory', 'record' => ['id' => 202, 'name' => 'Dock', 'qty' => 4, 'category' => ['id' => 12]]],
            ['type' => 'consumable', 'endpoint' => 'consumables', 'getter' => 'getConsumable', 'record' => ['id' => 203, 'name' => 'Toner', 'qty' => 4, 'category' => ['id' => 13], 'min_amt' => 2]],
            ['type' => 'component', 'endpoint' => 'components', 'getter' => 'getComponent', 'record' => ['id' => 204, 'name' => 'Memory', 'qty' => 4, 'category' => ['id' => 14]]],
        ];

        foreach ($cases as $case) {
            $id = $case['record']['id'];
            $snipe->shouldReceive($case['getter'])->once()->with($id)->andReturn($case['record']);
            $snipe->shouldReceive('updateRecord')->once()->with($case['endpoint'], $id, Mockery::type('array'))->andReturn(['status' => 'success']);
            $snipe->shouldReceive('flushCacheForAsset')->once()->with($case['type'], $id);
        }

        $this->app->instance(SnipeItService::class, $snipe);

        foreach ($cases as $case) {
            $id = $case['record']['id'];
            $fields = [
                'type' => $case['type'], 'name' => 'Updated '.$case['type'],
                'category_id' => $case['record']['category']['id'],
            ];
            if ($case['type'] === 'license') {
                $fields += ['seats' => 6, 'po_number' => 'PO-EDIT'];
            } else {
                $fields += ['qty' => 6];
            }

            $this->actingAs($user)->put('/asset/'.$id, $fields)->assertRedirect(route('asset.show', [
                'assetId' => $id,
                'type' => $case['type'],
            ]));
        }

        $this->assertSame(0, AssetStockHistory::count());
    }
}
