<?php

namespace Tests\Feature;

use App\Models\CctvRegularReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CctvRegularReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_cctv_regular_report_data(): void
    {
        $response = $this->getJson('/infra-report/cctv-regular/data');
        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_meta_and_data(): void
    {
        $user = User::factory()->create();

        $metaResponse = $this->actingAs($user)->getJson('/infra-report/cctv-regular/meta');
        $metaResponse->assertOk()
            ->assertJsonStructure(['current_year', 'current_week', 'default_date', 'default_doc_no', 'nvrs', 'locations']);

        $dataResponse = $this->actingAs($user)->getJson('/infra-report/cctv-regular/data');
        $dataResponse->assertOk()
            ->assertJsonStructure(['reports', 'stats']);
    }

    public function test_meta_can_derive_default_date_from_requested_iso_week(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/infra-report/cctv-regular/meta?year=2026&week=38');

        $response->assertOk()
            ->assertJsonPath('current_year', 2026)
            ->assertJsonPath('current_week', 38)
            ->assertJsonPath('default_date', '2026-09-14');
    }

    public function test_can_create_and_update_cctv_regular_report(): void
    {
        $user = User::factory()->create();

        $payload = [
            'location'     => 'ZGI BGR F1',
            'checked_by'   => 'MULIANA',
            'checked_date' => '2026-09-11',
            'week_number'  => 37,
            'year'         => 2026,
            'doc_no'       => 'IR/CCTV/ZGI/2026/37',
            'loading_area_data' => [
                'status'           => 'ok',
                'camera_qty'       => 10,
                'ok_qty'           => 10,
                'not_ok_qty'       => 0,
                'last_record_date' => '2026-06-14',
                'record_days'      => 89,
                'nvr_id'           => 'NVR F1.5',
            ],
            'beacukai_data' => [
                'status'           => 'ok',
                'camera_qty'       => 16,
                'ok_qty'           => 16,
                'not_ok_qty'       => 0,
                'last_record_date' => '2026-08-03',
                'record_days'      => 39,
                'nvr_id'           => 'NVR F1.1',
            ],
            'maintenance_data' => [
                'items' => [
                    ['camera_id' => 'CAM-01', 'action' => 'Lens Cleaning', 'act_date' => '2026-09-11', 'remarks' => 'Cleaned'],
                ],
            ],
            'signature_dept1_name'   => 'IT',
            'signature_dept1_signer' => 'MULIANA',
        ];

        $storeResponse = $this->actingAs($user)->postJson('/infra-report/cctv-regular', $payload);
        $storeResponse->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('cctv_regular_reports', [
            'doc_no'     => 'IR/CCTV/ZGI/2026/37',
            'checked_by' => 'MULIANA',
            'location'   => 'ZGI BGR F1',
        ]);

        $report = CctvRegularReport::first();
        $this->assertNotNull($report);

        // Test Show
        $showResponse = $this->actingAs($user)->getJson("/infra-report/cctv-regular/{$report->id}");
        $showResponse->assertOk()
            ->assertJsonPath('report.doc_no', 'IR/CCTV/ZGI/2026/37');

        // Test Print View
        $printResponse = $this->actingAs($user)->get("/infra-report/cctv-regular/{$report->id}/print");
        $printResponse->assertOk()
            ->assertSee('CCTV REGULER CHECK FORM')
            ->assertSee('IR/CCTV/ZGI/2026/37')
            ->assertSee('MULIANA');

        // Test Delete
        $deleteResponse = $this->actingAs($user)->deleteJson("/infra-report/cctv-regular/{$report->id}");
        $deleteResponse->assertOk();
        $this->assertDatabaseMissing('cctv_regular_reports', ['id' => $report->id]);
    }

    public function test_can_create_report_with_dynamic_areas_and_per_item_maintenance_photos(): void
    {
        $user = User::factory()->create();

        $payload = [
            'location'     => 'ZGI KRW F2',
            'checked_by'   => 'IRVAN',
            'checked_date' => '2026-09-14',
            'week_number'  => 38,
            'year'         => 2026,
            'doc_no'       => 'IR/CCTV/ZGI/2026/38',
            'areas_data'   => [
                [
                    'name'             => 'Area aaa',
                    'camera_qty'       => 8,
                    'ok_qty'           => 8,
                    'not_ok_qty'       => 0,
                    'last_record_date' => '2026-08-15', // 30 days difference
                    'nvr_id'           => 'NVR F2.1',
                    'nvr_remarks'      => 'Normal',
                ],
                [
                    'name'             => 'Area bbb',
                    'camera_qty'       => 16,
                    'ok_qty'           => 15,
                    'not_ok_qty'       => 1,
                    'last_record_date' => '2026-09-01', // 13 days difference
                    'nvr_id'           => 'NVR F2.2',
                    'nvr_remarks'      => 'Port 3 Loose',
                    'action_needed'    => [
                        ['camera_id' => 'CAM-03', 'action' => 'Re-crimping RJ45', 'due_date' => '2026-09-16', 'remarks' => 'Urgent'],
                    ],
                ],
                [
                    'name'             => 'Area ccc',
                    'camera_qty'       => 4,
                    'ok_qty'           => 4,
                    'not_ok_qty'       => 0,
                    'last_record_date' => '2026-09-10', // 4 days difference
                    'nvr_id'           => 'NVR F2.3',
                ],
            ],
            'maintenance_data' => [
                'items' => [
                    [
                        'camera_id'    => 'CAM-01',
                        'action'       => 'Pembersihan fisik lensa dan cover',
                        'act_date'     => '2026-09-14',
                        'remarks'      => 'Selesai',
                        'before_photo' => '/storage/cctv-regular-reports/dummy_before.jpg',
                        'after_photo'  => '/storage/cctv-regular-reports/dummy_after.jpg',
                    ],
                ],
            ],
            'signature_dept1_name'   => 'IT',
            'signature_dept1_signer' => 'IRVAN',
        ];

        $response = $this->actingAs($user)->postJson('/infra-report/cctv-regular', $payload);
        $response->assertOk()->assertJson(['success' => true]);

        $report = CctvRegularReport::where('doc_no', 'IR/CCTV/ZGI/2026/38')->first();
        $this->assertNotNull($report);
        $this->assertCount(3, $report->resolved_areas);
        $this->assertEquals('Area aaa', $report->resolved_areas[0]['name']);
        $this->assertEquals(30, $report->resolved_areas[0]['retention_days']);
        $this->assertEquals('Area bbb', $report->resolved_areas[1]['name']);
        $this->assertEquals(13, $report->resolved_areas[1]['retention_days']);
        $this->assertEquals('Area ccc', $report->resolved_areas[2]['name']);

        // Verify print view renders all 3 areas
        $printResponse = $this->actingAs($user)->get("/infra-report/cctv-regular/{$report->id}/print");
        $printResponse->assertOk()
            ->assertSee('Area aaa')
            ->assertSee('Area bbb')
            ->assertSee('Area ccc')
            ->assertSee('Pembersihan fisik lensa dan cover');
    }

    public function test_zdi_location_generates_correct_doc_no_and_company_name(): void
    {
        $user = User::factory()->create();

        $payload = [
            'location'     => 'ZDI TGR F3',
            'checked_by'   => 'DICKY',
            'checked_date' => '2026-09-14',
            'week_number'  => 38,
            'year'         => 2026,
            'areas_data'   => [
                [
                    'name'         => 'Loading Area',
                    'camera_qty'   => 10,
                    'ok_qty'       => 10,
                    'not_ok_qty'   => 0,
                    'last_record_date' => '2026-09-01',
                ],
            ],
            'maintenance_data' => [
                'items' => [],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/infra-report/cctv-regular', $payload);
        $response->assertOk();

        $report = CctvRegularReport::where('location', 'ZDI TGR F3')->first();
        $this->assertNotNull($report);
        $this->assertStringStartsWith('IR/CCTV/ZDI/2026/38', $report->doc_no);
        $this->assertEquals('PT. ZINUS DREAM INDONESIA', $report->company_name);

        $printResponse = $this->actingAs($user)->get("/infra-report/cctv-regular/{$report->id}/print");
        $printResponse->assertOk()
            ->assertSee('PT. ZINUS DREAM INDONESIA')
            ->assertSee('IR/CCTV/ZDI/2026/38');
    }

    public function test_can_complete_and_lock_report_preventing_edits_and_deletions(): void
    {
        $user = User::factory()->create();

        $report = CctvRegularReport::create([
            'doc_no'                 => 'IR/CCTV/ZGI/2026/38',
            'location'               => 'ZGI KRW F2',
            'company_name'           => 'PT. ZINUS GLOBAL INDONESIA',
            'checked_by'             => 'Muliana',
            'checked_date'           => '2026-09-14',
            'week_number'            => 38,
            'year'                   => 2026,
            'areas_data'             => [],
            'maintenance_data'       => ['items' => []],
            'status'                 => 'draft',
            'created_by'             => $user->id,
        ]);

        $this->assertEquals('draft', $report->status);

        // Complete the report
        $completeRes = $this->actingAs($user)->postJson("/infra-report/cctv-regular/{$report->id}/complete");
        $completeRes->assertOk();
        $this->assertEquals('completed', $report->fresh()->status);

        // Attempt to update should return 422
        $updateRes = $this->actingAs($user)->putJson("/infra-report/cctv-regular/{$report->id}", [
            'location'     => 'ZGI KRW F2',
            'checked_by'   => 'Muliana Updated',
            'checked_date' => '2026-09-14',
            'week_number'  => 38,
            'year'         => 2026,
        ]);
        $updateRes->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Attempt to delete should return 422
        $deleteRes = $this->actingAs($user)->deleteJson("/infra-report/cctv-regular/{$report->id}");
        $deleteRes->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // View and print/pdf should still be accessible
        $viewRes = $this->actingAs($user)->getJson("/infra-report/cctv-regular/{$report->id}");
        $viewRes->assertOk();

        $printRes = $this->actingAs($user)->get("/infra-report/cctv-regular/{$report->id}/print");
        $printRes->assertOk();
    }
}

