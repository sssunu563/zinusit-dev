<?php

namespace Tests\Feature;

use App\Exports\InfraReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfraReportExportSheetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_generates_all_seven_sheets_with_dashboard(): void
    {
        $export = new InfraReportExport('2026-07-17', '2026-07-23');

        $method = new \ReflectionMethod($export, 'buildSpreadsheet');
        $method->setAccessible(true);
        /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet */
        $spreadsheet = $method->invoke($export);

        $sheetNames = $spreadsheet->getSheetNames();

        $expectedSheets = [
            'Weekly Dashboard',
            'Network Devices',
            'NVR & CCTV',
            'Servers',
            'Bandwidth Traffic',
            'Helpdesk Tickets',
            'Maintenance Logs',
        ];

        $this->assertSame($expectedSheets, $sheetNames);

        // Check Dashboard sheet content
        $ws = $spreadsheet->getSheetByName('Weekly Dashboard');
        $this->assertNotNull($ws);
        $this->assertSame('ZINUS IDN | WEEKLY INFRA REPORT', $ws->getCell('B2')->getValue());
        $this->assertStringContainsString('Infrastructure health & incident overview', $ws->getCell('B4')->getValue());
        $this->assertSame('SYSTEM UPTIME', $ws->getCell('B6')->getValue());
        $this->assertSame('AVG BANDWIDTH', $ws->getCell('E6')->getValue());
        $this->assertSame('PC ISSUES RESOLVED', $ws->getCell('H6')->getValue());
        $this->assertSame('FAILED DEVICES', $ws->getCell('K6')->getValue());
        $this->assertSame('BRANCH HEALTH', $ws->getCell('B10')->getValue());
        $this->assertSame('BANDWIDTH SNAPSHOT', $ws->getCell('H10')->getValue());
    }

    public function test_export_endpoint_returns_download_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/infra-report/export?from=2026-07-17&to=2026-07-23');

        $response->assertOk();
        $this->assertStringContainsString('Weekly_Infra_Report_2026-07-17_to_2026-07-23.xlsx', $response->headers->get('content-disposition'));
    }
}
