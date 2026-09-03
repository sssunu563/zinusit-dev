<?php

namespace Tests\Feature;

use App\Http\Controllers\InspectionController;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class InspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('inspection.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_inspection_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('inspection.index'));
        $response->assertOk();
    }

    public function test_dead_hardware_inspection_marks_asset_as_broken_without_checkin()
    {
        $inspection = Inspection::create([
            'report_id' => 'IR-TEST-2501-00001',
            'report_type' => 'Inspection',
            'company' => 'PT Test',
            'department' => 'IT',
            'user' => 'Budi',
            'email' => 'budi@example.com',
            'date' => now()->toDateString(),
            'location' => 'Jakarta',
            'device_category' => 'laptop',
            'device_name' => 'Laptop Test',
            'asset_tag' => 'TAG-001',
            'serial_number' => 'SN-001',
            'asset_snapshot' => json_encode([
                'id' => 99,
                'asset_type' => 'assets',
                'name' => 'Laptop Test',
            ]),
            'snipeit_asset_id' => 99,
            'checked_by' => 'IT Staff',
            'checked_date' => now()->toDateString(),
            'issue_description' => 'Laptop mati total dan tidak bisa dinyalakan.',
            'solution' => 'Status asset harus diperbaiki.',
            'remarks' => 'Perlu pengecekan lebih lanjut.',
        ]);

        $snipe = \Mockery::mock(\App\Services\SnipeItService::class);
        $snipe->shouldReceive('request')->with('hardware/99')->andReturn(['id' => 99, 'assigned_to' => ['id' => 7]]);
        $snipe->shouldReceive('checkinAsset')
            ->with('hardware', 99, \Mockery::on(fn ($payload) => str_contains((string) ($payload['note'] ?? ''), 'Inspection')))
            ->once()
            ->andReturn(['status' => 'success']);
        $snipe->shouldReceive('fetchRows')->with('statuslabels')->andReturn([
            ['id' => 5, 'name' => 'Broken'],
        ]);
        $snipe->shouldReceive('updateRecord')
            ->with('hardware', 99, \Mockery::on(fn ($payload) => (int) ($payload['status_id'] ?? 0) === 5 && str_contains($payload['notes'] ?? '', 'Inspection')))
            ->once()
            ->andReturn(['status' => 'success']);
        $snipe->shouldReceive('flushCacheForAsset')->with('assets', 99)->once();
        $snipe->shouldReceive('uploadFile')->with('hardware', 99, \Mockery::type('string'), \Mockery::type('string'), \Mockery::type('string'))->once()->andReturn(['status' => 'success']);

        $this->app->instance(\App\Services\SnipeItService::class, $snipe);

        $response = (new InspectionController())->complete(new Request(), $inspection);

        $this->assertTrue($response->isRedirect());
        $inspection->refresh();
        $this->assertSame('success', $inspection->snipeit_sync_status);
    }

    public function test_component_inspection_checks_in_and_reduces_quantity()
    {
        $inspection = Inspection::create([
            'report_id' => 'IR-TEST-2501-00002',
            'report_type' => 'Inspection',
            'company' => 'PT Test',
            'department' => 'IT',
            'user' => 'Budi',
            'email' => 'budi@example.com',
            'date' => now()->toDateString(),
            'location' => 'Jakarta',
            'device_category' => 'component',
            'device_name' => 'SSD 256GB',
            'asset_tag' => 'TAG-SSD',
            'serial_number' => 'SSD-100',
            'asset_snapshot' => json_encode([
                'id' => 101,
                'asset_type' => 'component',
                'name' => 'SSD 256GB',
            ]),
            'snipeit_asset_id' => 101,
            'user_snipeit_id' => 7,
            'checked_by' => 'IT Staff',
            'checked_date' => now()->toDateString(),
            'issue_description' => 'SSD mengalami bad sector.',
            'solution' => 'Component akan dilaporkan untuk maintenance.',
            'remarks' => 'Tidak perlu checkin.',
        ]);

        $snipe = \Mockery::mock(\App\Services\SnipeItService::class);
        $snipe->shouldReceive('createRecord')->never();
        $snipe->shouldReceive('request')->with('components/101/assets', ['limit' => 50], true)
            ->once()->andReturn(['rows' => [['id' => 555, 'assigned_to' => ['id' => 7]]]]);
        $snipe->shouldReceive('checkinAsset')
            ->with('components', 555, \Mockery::on(fn ($payload) => str_contains($payload['note'] ?? '', 'Inspection')))
            ->once()->andReturn(['status' => 'success']);
        $snipe->shouldReceive('request')->with('components/101')->once()->andReturn(['qty' => 2]);
        $snipe->shouldReceive('updateRecord')
            ->with('components', 101, \Mockery::on(fn ($payload) => ($payload['qty'] ?? null) === 1))
            ->once()->andReturn(['status' => 'success']);
        $snipe->shouldReceive('flushCacheForAsset')->with('component', 101)->once();
        $snipe->shouldReceive('uploadFile')->with('components', 101, \Mockery::type('string'), \Mockery::type('string'), \Mockery::type('string'))->once()->andReturn(['status' => 'success']);

        $this->app->instance(\App\Services\SnipeItService::class, $snipe);

        $response = (new InspectionController())->complete(new Request(), $inspection);

        $this->assertTrue($response->isRedirect());
        $inspection->refresh();
        $this->assertSame('success', $inspection->snipeit_sync_status);
        $this->assertStringContainsString('quantity reduced by 1', $inspection->snipeit_sync_log);
    }

    public function test_accessory_inspection_checks_in_and_reduces_quantity()
    {
        $inspection = Inspection::create([
            'report_id' => 'IR-TEST-2501-00003',
            'report_type' => 'Inspection Part / Accessories / Other',
            'company' => 'PT Test',
            'department' => 'IT',
            'user' => 'Budi',
            'email' => 'budi@example.com',
            'date' => now()->toDateString(),
            'location' => 'Jakarta',
            'device_category' => 'other',
            'device_name' => 'Mouse Wireless',
            'asset_tag' => 'TAG-MOUSE',
            'serial_number' => 'MSE-001',
            'asset_snapshot' => json_encode([
                'id' => 102,
                'asset_type' => 'accessories',
                'name' => 'Mouse Wireless',
            ]),
            'snipeit_asset_id' => 102,
            'user_snipeit_id' => 7,
            'checked_by' => 'IT Staff',
            'checked_date' => now()->toDateString(),
            'issue_description' => 'Mouse sudah aus.',
            'solution' => 'Masukkan ke maintenance record.',
            'remarks' => 'Tidak perlu checkin.',
        ]);

        $snipe = \Mockery::mock(\App\Services\SnipeItService::class);
        $snipe->shouldReceive('createRecord')->never();
        $snipe->shouldReceive('request')->with('accessories/102/checkedout', ['limit' => 50], true)
            ->once()->andReturn(['rows' => [['id' => 777, 'assigned_to' => ['id' => 7]]]]);
        $snipe->shouldReceive('checkinAsset')
            ->with('accessories', 777, \Mockery::on(fn ($payload) => str_contains($payload['note'] ?? '', 'Inspection')))
            ->once()->andReturn(['status' => 'success']);
        $snipe->shouldReceive('request')->with('accessories/102')->once()->andReturn(['qty' => 10]);
        $snipe->shouldReceive('updateRecord')
            ->with('accessories', 102, \Mockery::on(fn ($payload) => ($payload['qty'] ?? null) === 9))
            ->once()->andReturn(['status' => 'success']);
        $snipe->shouldReceive('flushCacheForAsset')->with('component', 102)->once();
        $snipe->shouldReceive('uploadFile')->with('accessories', 102, \Mockery::type('string'), \Mockery::type('string'), \Mockery::type('string'))->once()->andReturn(['status' => 'success']);

        $this->app->instance(\App\Services\SnipeItService::class, $snipe);

        $response = (new InspectionController())->complete(new Request(), $inspection);

        $this->assertTrue($response->isRedirect());
        $inspection->refresh();
        $this->assertSame('success', $inspection->snipeit_sync_status);
        $this->assertStringContainsString('quantity reduced by 1', $inspection->snipeit_sync_log);
    }
}
