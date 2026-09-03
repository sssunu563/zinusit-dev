<?php

namespace Tests\Feature;

use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_report_logs_page(): void
    {
        $response = $this->get('/report-logs');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_report_logs_page(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'auto_fetch',
            'item_type' => 'ServerOperation',
            'note' => 'Auto fetch Server Operation: 61 OK, 0 gagal.',
            'log_meta' => [
                'date' => '2026-05-22',
                'total_ok' => 61,
            ],
        ]);

        $response = $this->actingAs($user)->get('/report-logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ReportLogs/Index')
            ->where('logs.data.0.action_type', 'auto_fetch')
            ->where('logs.data.0.report_type', 'server')
            ->where('logs.data.0.report_name', 'Server Operation'));
    }

    public function test_authenticated_users_can_filter_report_logs_by_report_type(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'auto_fetch',
            'item_type' => 'ServerOperation',
            'note' => 'Server fetch',
        ]);

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'auto_fetch',
            'item_type' => 'CctvOperation',
            'note' => 'CCTV fetch',
        ]);

        $response = $this->actingAs($user)->get('/report-logs?filter_report=server');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ReportLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.report_type', 'server'));
    }

    public function test_authenticated_users_can_export_report_logs_as_csv(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'auto_fetch',
            'item_type' => 'ServerOperation',
            'note' => 'Server fetch export',
            'log_meta' => ['date' => '2026-05-22'],
        ]);

        $response = $this->actingAs($user)->get('/report-logs/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Waktu,"Otorisator / Trigger","Jenis Report",Aksi,Catatan,"Detail Hasil"', $csv);
        $this->assertStringContainsString('Server Operation', $csv);
        $this->assertStringContainsString('AUTO_FETCH', $csv);
    }

    public function test_all_reports_item_type_does_not_throw_class_not_found(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'auto_fetch',
            'item_type' => 'AllReports',
            'note' => 'Auto fetch semua report selesai',
            'log_meta' => [
                'date' => '2026-05-22',
                'total_ok' => 491,
                'total_fail' => 3,
            ],
        ]);

        $response = $this->actingAs($user)->get('/report-logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('ReportLogs/Index')
            ->where('logs.data.0.report_name', 'All Reports Sync')
            ->where('logs.data.0.report_type', 'all'));
    }
}
