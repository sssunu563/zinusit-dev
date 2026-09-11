<?php

namespace Tests\Feature;

use App\Models\CctvDevice;
use App\Models\IspSlaContract;
use App\Models\NetworkDevice;
use App\Models\NetworkMaintenanceLog;
use App\Models\ServerDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfraReportDeviceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_access_device_settings_page(): void
    {
        $response = $this->actingAs($this->user)->get('/infra-report/device-settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Report/InfraReport/DeviceSettings')
            ->has('devices')
            ->has('bandwidthContracts')
            ->has('failedDevices')
        );
    }

    public function test_user_can_toggle_individual_device_setting(): void
    {
        $device = NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-toggle-1',
            'device_name' => 'Switch Core Bogor',
            'ip_address' => '10.10.1.1',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => false,
        ]);

        $response = $this->actingAs($this->user)->put("/infra-report/device-settings/network/{$device->id}", [
            'included' => false,
        ]);

        $response->assertOk();
        $this->assertTrue($device->fresh()->is_excluded);

        // Toggle back to included
        $response2 = $this->actingAs($this->user)->put("/infra-report/device-settings/network/{$device->id}", [
            'included' => true,
        ]);

        $response2->assertOk();
        $this->assertFalse($device->fresh()->is_excluded);
    }

    public function test_user_can_batch_update_device_settings(): void
    {
        $dev1 = NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-batch-1',
            'device_name' => 'Device 1',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => false,
        ]);

        $dev2 = NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-batch-2',
            'device_name' => 'Device 2',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => false,
        ]);

        $dev3 = NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-batch-3',
            'device_name' => 'Device 3',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => false,
        ]);

        // Only include dev1 and dev3 for F1 Bogor Network
        $response = $this->actingAs($this->user)->post('/infra-report/device-settings/batch', [
            'type' => 'network',
            'site' => 'F1 Bogor',
            'included_ids' => [$dev1->id, $dev3->id],
        ]);

        $response->assertOk();
        $this->assertFalse($dev1->fresh()->is_excluded);
        $this->assertTrue($dev2->fresh()->is_excluded);
        $this->assertFalse($dev3->fresh()->is_excluded);
    }

    public function test_user_can_update_bandwidth_capacity(): void
    {
        $contract = IspSlaContract::create([
            'location' => 'Bogor',
            'fct' => 'F1',
            'provider' => 'ISAT',
            'bandwidth' => 180.0,
            'target_pct' => 99.5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($this->user)->post('/infra-report/bandwidth-capacity', [
            'id' => $contract->id,
            'bandwidth' => 250.0,
            'target_pct' => 99.8,
        ]);

        $response->assertOk();
        $this->assertEquals(250.0, (float)$contract->fresh()->bandwidth);
        $this->assertEquals(99.8, (float)$contract->fresh()->target_pct);
    }

    public function test_user_can_create_update_and_delete_maintenance_log(): void
    {
        $device = NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-maint-1',
            'device_name' => 'Router Edge',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => false,
        ]);

        // Create log
        $response = $this->actingAs($this->user)->post('/infra-report/maintenance-log', [
            'device_type' => 'network',
            'device_id' => $device->id,
            'started_at' => '2026-09-01T08:00',
            'resolved_at' => '2026-09-02T12:00',
            'event_type' => 'maintenance',
            'notes' => 'Perbaikan FO Gedung A',
        ]);

        $response->assertOk();
        $logId = $response->json('log.id');
        $this->assertNotNull($logId);

        $log = NetworkMaintenanceLog::find($logId);
        $this->assertNotNull($log);
        $this->assertSame('Perbaikan FO Gedung A', $log->notes);
        $this->assertSame('closed', $log->status);

        // Update log
        $updateResponse = $this->actingAs($this->user)->post('/infra-report/maintenance-log', [
            'id' => $logId,
            'device_type' => 'network',
            'device_id' => $device->id,
            'started_at' => '2026-09-01T08:00',
            'resolved_at' => '2026-09-03T10:00',
            'event_type' => 'down',
            'notes' => 'Catatan revisi perbaikan FO',
        ]);

        $updateResponse->assertOk();
        $this->assertSame('Catatan revisi perbaikan FO', $log->fresh()->notes);

        // Delete log
        $deleteResponse = $this->actingAs($this->user)->delete("/infra-report/maintenance-log/network/{$logId}");
        $deleteResponse->assertOk();
        $this->assertNull(NetworkMaintenanceLog::find($logId));
    }
}
