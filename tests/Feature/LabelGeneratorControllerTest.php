<?php

namespace Tests\Feature;

use App\Http\Controllers\LabelGeneratorController;
use App\Services\SnipeItService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Mockery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class LabelGeneratorControllerTest extends TestCase
{
    public function test_label_generator_index_search_uses_hardware_list_fetch_rows(): void
    {
        $snipe = Mockery::mock(SnipeItService::class);
        $snipe->shouldReceive('fetchRows')
            ->once()
            ->with('hardware', ['search' => 'abc', 'limit' => 30])
            ->andReturn([
                [
                    'id' => 1,
                    'name' => 'Asset One',
                    'asset_tag' => 'AS-001',
                    'serial' => 'SN-001',
                    'model' => ['name' => 'Model 1'],
                    'category' => ['name' => 'Laptop'],
                    'location' => ['name' => 'Bogor'],
                    'company' => ['name' => 'Zinus'],
                    'status_label' => ['name' => 'Archived', 'status_type' => 'archived'],
                ],
            ]);

        $controller = new LabelGeneratorController($snipe);
        $request = Request::create('/label-generator', 'GET', ['search' => 'abc']);

        $response = $controller->index($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_label_generator_pdf_accepts_multiple_ids_and_routes_through_batch_label_pdf(): void
    {
        $snipe = Mockery::mock(SnipeItService::class);
        $snipe->shouldReceive('getHardware')
            ->once()
            ->with(1)
            ->andReturn([
                'id' => 1,
                'name' => 'Asset One',
                'asset_tag' => 'AS-001',
                'serial' => 'SN-001',
                'location' => ['name' => 'Bogor'],
            ]);

        $snipe->shouldReceive('getHardware')
            ->once()
            ->with(2)
            ->andReturn([
                'id' => 2,
                'name' => 'Asset Two',
                'asset_tag' => 'AS-002',
                'serial' => 'SN-002',
                'location' => ['name' => 'Jakarta'],
            ]);

        $controller = new LabelGeneratorController($snipe);
        $request = Request::create('/label-generator/pdf?ids[]=1&ids[]=2', 'GET', ['size' => 'xs']);

        $response = $controller->pdf($request);

        $this->assertTrue(
            $response instanceof BinaryFileResponse || $response instanceof RedirectResponse,
            'Batch label print route should resolve to label-generator/pdf and return a PDF response or redirect when browser PDF path is unavailable.',
        );
    }
}
