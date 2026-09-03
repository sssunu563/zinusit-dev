<?php

namespace Tests\Feature;

use App\Models\Inspection;
use App\Models\Stb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BankDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_bank_documents_page(): void
    {
        $response = $this->get('/bank-documents');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_bank_documents_page(): void
    {
        $user = User::factory()->create();

        Stb::create([
            'deliver_date' => now()->toDateString(),
            'status' => 1,
            'user_name' => 'John Doe',
            'user_dept' => 'IT',
            'document_type' => 'handover',
            'movement_type' => 'out',
            'completed_pdf_path' => 'stb-pdfs/test.pdf',
        ]);

        $response = $this->actingAs($user)->get('/bank-documents');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BankDocuments/Index')
            ->has('documents.data')
            ->has('stats')
            ->where('stats.total', 1)
            ->where('stats.stb', 1));
    }

    public function test_authenticated_users_can_filter_bank_documents_by_type(): void
    {
        $user = User::factory()->create();

        Stb::create([
            'deliver_date' => now()->toDateString(),
            'status' => 1,
            'user_name' => 'STB User',
            'document_type' => 'handover',
            'movement_type' => 'out',
        ]);

        Inspection::create([
            'report_id' => 'IR-TEST-001',
            'report_type' => 'regular',
            'user' => 'Inspection User',
            'checked_by' => 'IT Staff',
            'checked_date' => now()->toDateString(),
            'department' => 'IT',
            'company' => 'Zinus',
            'location' => 'Factory',
            'device_category' => 'laptop',
            'device_name' => 'ThinkPad',
            'date' => now(),
            'status' => 'completed',
            'issue_description' => 'Test issue description',
            'solution' => 'Test solution',
        ]);

        $response = $this->actingAs($user)->get('/bank-documents?filter_type=inspection');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BankDocuments/Index')
            ->where('filters.filter_type', 'inspection')
            ->where('documents.total', 1)
            ->where('documents.data.0.doc_type', 'inspection'));
    }

    public function test_authenticated_users_can_export_bank_documents_csv(): void
    {
        $user = User::factory()->create();

        Stb::create([
            'deliver_date' => now()->toDateString(),
            'status' => 1,
            'user_name' => 'Export User',
            'user_dept' => 'Finance',
            'document_type' => 'handover',
            'movement_type' => 'out',
        ]);

        $response = $this->actingAs($user)->get('/bank-documents/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Penerima / Pemilik', $csv);
        $this->assertStringContainsString('Export User', $csv);
        $this->assertStringContainsString('Finance', $csv);
    }
}
