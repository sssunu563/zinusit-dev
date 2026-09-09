<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StbAssetGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_generating_stb_from_consumable_detail_keeps_consumable_category_and_reference(): void
    {
        $user = User::factory()->create();
        $snipe = Mockery::mock(SnipeItService::class);
        $snipe->shouldReceive('getConsumable')->twice()->with(77)->andReturn([
            'id' => 77,
            'name' => 'Tinta Black Epson',
            'model_number' => 'T6641',
            'qty' => 10,
        ]);
        $this->app->instance(SnipeItService::class, $snipe);

        $response = $this->actingAs($user)->get('/stb/create?documentType=handover&movementType=out&assetType=consumable&selectedAssetIds[]=77');

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Stb/Create')
            ->where('initialData.items.0.nama', 'Tinta Black Epson')
            ->where('initialData.items.0.kategori', 'consumable')
            ->where('initialData.items.0.type', 'Consumable')
            ->where('initialData.items.0.inventory_number', 'T6641')
            ->where('initialData.items.0.snipeit_asset_id', 77));
    }

    public function test_generating_stb_from_other_stock_details_keeps_their_categories_and_references(): void
    {
        $user = User::factory()->create();
        $snipe = Mockery::mock(SnipeItService::class);
        $cases = [
            ['type' => 'license', 'id' => 78, 'getter' => 'getLicense', 'name' => 'Office', 'reference' => ['product_key' => 'LIC-78'], 'category' => 'license', 'label' => 'License'],
            ['type' => 'accessories', 'id' => 79, 'getter' => 'getAccessory', 'name' => 'Dock', 'reference' => ['model_number' => 'DOCK-79'], 'category' => 'accessories', 'label' => 'Accessory'],
            ['type' => 'component', 'id' => 80, 'getter' => 'getComponent', 'name' => 'Memory', 'reference' => ['serial' => 'RAM-80'], 'category' => 'component', 'label' => 'Component'],
        ];

        foreach ($cases as $case) {
            $snipe->shouldReceive($case['getter'])->twice()->with($case['id'])->andReturn(array_merge(
                ['id' => $case['id'], 'name' => $case['name'], 'qty' => 1],
                $case['reference'],
            ));
        }
        $this->app->instance(SnipeItService::class, $snipe);

        foreach ($cases as $case) {
            $response = $this->actingAs($user)->get('/stb/create?documentType=handover&movementType=out&assetType='.$case['type'].'&selectedAssetIds[]='.$case['id']);

            $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Stb/Create')
                ->where('initialData.items.0.kategori', $case['category'])
                ->where('initialData.items.0.type', $case['label'])
                ->where('initialData.items.0.snipeit_asset_id', $case['id']));
        }
    }
}
