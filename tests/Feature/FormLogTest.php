<?php

namespace Tests\Feature;

use App\Models\ActionLog;
use App\Models\Stb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FormLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_form_logs_page(): void
    {
        $response = $this->get('/form-logs');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_form_logs_page(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'stb_complete',
            'item_type' => Stb::class,
            'item_id' => 1,
            'note' => 'Dokumen Diselesaikan: ZGI-2609-0001',
            'log_meta' => [
                'doc_no' => 'ZGI-2609-0001',
                'pdf_path' => 'stb-pdfs/zgi-2609-0001.pdf',
            ],
        ]);

        $response = $this->actingAs($user)->get('/form-logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FormLogs/Index')
            ->where('logs.data.0.action_type', 'stb_complete')
            ->where('logs.data.0.form_type', 'stb')
            ->where('logs.data.0.doc_no', 'ZGI-2609-0001'));
    }

    public function test_authenticated_users_can_filter_form_logs_by_search_and_form_type(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'sign',
            'item_type' => Stb::class,
            'item_id' => 1,
            'note' => 'Tanda tangan it_approved ditambahkan pada HANDOVER #1',
            'log_meta' => ['role' => 'it_approved', 'doc_no' => 'ZGI-2609-0001'],
        ]);

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'created',
            'item_type' => \App\Models\Inspection::class,
            'item_id' => 2,
            'note' => 'Inspection IR-ZGI-2609-00002 created',
            'log_meta' => ['report_id' => 'IR-ZGI-2609-00002'],
        ]);

        $response = $this->actingAs($user)->get('/form-logs?filter_form=stb&search=it_approved');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('FormLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.action_type', 'sign')
            ->where('logs.data.0.form_type', 'stb'));
    }

    public function test_authenticated_users_can_export_form_logs_as_csv(): void
    {
        $user = User::factory()->create();

        ActionLog::create([
            'user_id' => $user->id,
            'action_type' => 'sign',
            'item_type' => Stb::class,
            'item_id' => 1,
            'note' => 'Tanda tangan it_approved pada STB ZGI-2609-0001',
            'log_meta' => ['role' => 'it_approved', 'doc_no' => 'ZGI-2609-0001'],
        ]);

        $response = $this->actingAs($user)->get('/form-logs/export?filter_form=stb');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Waktu,"Otorisator / User","Jenis Form","No. Dokumen",Operasi,"Role TTD",Catatan,Metadata', $csv);
        $this->assertStringContainsString('ZGI-2609-0001', $csv);
        $this->assertStringContainsString('SIGN', $csv);
    }
}
