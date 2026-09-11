<?php

namespace Tests\Feature;

use App\Exports\InfraReportExport;
use App\Models\NetworkDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfraReportExportExcludeTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uptime_report_respects_excluded_flag(): void
    {
        NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-1',
            'device_name' => 'Included Network Device',
            'ip_address' => '10.0.0.10',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => false,
        ]);

        NetworkDevice::create([
            'source' => 'manual',
            'source_instance' => 'test',
            'source_id' => 'net-2',
            'device_name' => 'Excluded Network Device',
            'ip_address' => '10.0.0.11',
            'site' => 'F1 Bogor',
            'is_active' => true,
            'is_excluded' => true,
        ]);

        $export = new InfraReportExport('2026-09-01', '2026-09-07');
        $method = new \ReflectionMethod($export, 'getUptimeReport');
        $method->setAccessible(true);

        $rows = $method->invoke($export, ['F1 Bogor'], 'network');

        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['qty']);
    }
}
