<?php

namespace Tests\Feature;

use App\Models\AuditItem;
use App\Models\AuditSession;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.snipeit.url', 'http://snipe.test');
        config()->set('services.snipeit.token', 'test-token');
    }

    public function test_new_session_snapshots_active_assets_as_missing_and_excludes_broken_assets(): void
    {
        Http::fake([
            'http://snipe.test/api/v1/hardware*' => Http::response([
                'rows' => [
                    [
                        'id' => 10,
                        'name' => 'Laptop Active',
                        'asset_tag' => 'AST-010',
                        'serial' => 'SN-010',
                        'location' => ['name' => 'IT Room'],
                        'assigned_to' => ['name' => 'Budi'],
                        'status_label' => ['name' => 'Ready to Deploy', 'status_type' => 'deployable'],
                    ],
                    [
                        'id' => 11,
                        'name' => 'Laptop Broken',
                        'asset_tag' => 'AST-011',
                        'serial' => 'SN-011',
                        'status_label' => ['name' => 'Broken', 'status_type' => 'archived'],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/audit', [
            'name' => 'SO Q1 2026',
            'description' => 'Gedung utama',
        ]);

        $session = AuditSession::firstOrFail();
        $response->assertRedirect(route('audit.show', $session));
        $this->assertDatabaseCount('audit_items', 1);
        $this->assertDatabaseHas('audit_items', [
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 10,
            'asset_name' => 'Laptop Active',
            'status' => 'Missing',
            'verified_at' => null,
        ]);
    }

    public function test_unknown_scan_returns_validation_error_without_redirect(): void
    {
        Http::fake([
            'http://snipe.test/api/v1/hardware*' => Http::response(['rows' => []]),
        ]);

        $user = User::factory()->create();
        $session = AuditSession::create([
            'name' => 'SO Q1 2026',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson("/audit/{$session->id}/scan", [
            'search' => 'UNKNOWN-ASSET',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Asset tidak tersedia atau tidak ditemukan di Snipe-IT.');
    }

    public function test_open_legacy_empty_session_is_seeded_when_opened(): void
    {
        Http::fake([
            'http://snipe.test/api/v1/hardware*' => Http::response([
                'rows' => [[
                    'id' => 20,
                    'name' => 'Legacy Laptop',
                    'asset_tag' => 'AST-020',
                    'serial' => 'SN-020',
                    'status_label' => ['name' => 'Ready to Deploy', 'status_type' => 'deployable'],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $session = AuditSession::create([
            'name' => 'SO Lama',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get("/audit/{$session->id}")->assertOk();

        $this->assertDatabaseHas('audit_items', [
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 20,
            'status' => 'Missing',
        ]);
    }

    public function test_mismatch_requires_a_physical_change(): void
    {
        $user = User::factory()->create();
        $session = AuditSession::create([
            'name' => 'SO Q1 2026',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);
        $item = AuditItem::create([
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 10,
            'asset_tag' => 'AST-010',
            'serial' => 'SN-010',
            'asset_name' => 'Laptop Active',
            'status' => 'Missing',
            'expected_location' => 'IT Room',
            'expected_user' => 'Budi',
            'verified_by' => null,
        ]);

        $response = $this->actingAs($user)->postJson("/audit/{$session->id}/verify", [
            'snipeit_asset_id' => $item->snipeit_asset_id,
            'asset_tag' => $item->asset_tag,
            'serial' => $item->serial,
            'status' => 'Mismatch',
            'physical_location' => 'IT Room',
            'physical_user' => 'Budi',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Mismatch harus memiliki perubahan lokasi atau pengguna fisik.');
        $this->assertDatabaseHas('audit_items', [
            'id' => $item->id,
            'status' => 'Missing',
            'verified_at' => null,
        ]);
    }

    public function test_mismatch_can_update_asset_data_before_verification(): void
    {
        Http::fake([
            'http://snipe.test/api/v1/locations*' => Http::response([
                'rows' => [['id' => 7, 'name' => 'Cambodia']],
            ]),
            'http://snipe.test/api/v1/hardware/*' => Http::response([
                'status' => 'success',
            ]),
        ]);

        $user = User::factory()->create();
        $session = AuditSession::create([
            'name' => 'SO Q1 2026',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);
        $item = AuditItem::create([
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 12,
            'asset_tag' => 'AST-012',
            'serial' => 'SN-012',
            'asset_name' => 'Old Name',
            'status' => 'Missing',
            'verified_by' => null,
            'expected_location' => 'F1',
            'expected_user' => 'Budi',
        ]);

        $response = $this->actingAs($user)->putJson("/audit/{$session->id}/asset/{$item->id}", [
            'name' => 'Updated Name',
            'asset_tag' => 'AST-012-NEW',
            'serial' => 'SN-012-NEW',
            'location' => 'Cambodia',
            'notes' => 'Ditemukan di lokasi baru',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('audit_items', [
            'id' => $item->id,
            'asset_name' => 'Updated Name',
            'asset_tag' => 'AST-012-NEW',
            'physical_location' => 'Cambodia',
            'notes' => 'Ditemukan di lokasi baru',
        ]);
    }

    public function test_empty_open_session_can_be_cancelled_and_deletes_its_items(): void
    {
        $user = User::factory()->create();
        $session = AuditSession::create([
            'name' => 'SO Q1 2026',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);
        AuditItem::create([
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 30,
            'asset_tag' => 'AST-030',
            'serial' => 'SN-030',
            'asset_name' => 'Laptop',
            'status' => 'Missing',
            'verified_by' => null,
        ]);

        $response = $this->actingAs($user)->delete("/audit/{$session->id}");

        $response->assertRedirect(route('audit.index'));
        $this->assertDatabaseMissing('audit_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('audit_items', ['audit_session_id' => $session->id]);
    }

    public function test_session_with_activity_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $session = AuditSession::create([
            'name' => 'SO Aktif',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);
        AuditItem::create([
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 31,
            'asset_tag' => 'AST-031',
            'serial' => 'SN-031',
            'asset_name' => 'Laptop',
            'status' => 'Match',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->delete("/audit/{$session->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('audit_sessions', ['id' => $session->id]);
    }

    public function test_already_verified_asset_cannot_be_scanned_again(): void
    {
        Http::fake([
            'http://snipe.test/api/v1/hardware*' => Http::response([
                'rows' => [[
                    'id' => 40,
                    'name' => 'Audited Laptop',
                    'asset_tag' => 'AST-040',
                    'serial' => 'SN-040',
                    'status_label' => ['name' => 'Active', 'status_type' => 'deployable'],
                ]],
            ]),
        ]);

        $user = User::factory()->create(['name' => 'Auditor Test']);
        $session = AuditSession::create([
            'name' => 'SO Audit',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);
        AuditItem::create([
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 40,
            'asset_tag' => 'AST-040',
            'serial' => 'SN-040',
            'asset_name' => 'Audited Laptop',
            'status' => 'Match',
            'verified_by' => $user->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson("/audit/{$session->id}/scan", [
            'search' => 'AST-040',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('already_audited', true)
            ->assertJsonPath('verified_by', 'Auditor Test');
    }

    public function test_audit_verification_is_recorded_in_activity_log_with_auditor(): void
    {
        $user = User::factory()->create(['name' => 'Audit User']);
        $session = AuditSession::create([
            'name' => 'SO Logging',
            'status' => 'Open',
            'created_by' => $user->id,
        ]);
        $item = AuditItem::create([
            'audit_session_id' => $session->id,
            'snipeit_asset_id' => 50,
            'asset_tag' => 'AST-050',
            'serial' => 'SN-050',
            'asset_name' => 'Logged Laptop',
            'status' => 'Missing',
            'expected_location' => 'F1',
            'expected_user' => 'Available',
            'verified_by' => null,
        ]);

        $this->actingAs($user)->postJson("/audit/{$session->id}/verify", [
            'snipeit_asset_id' => $item->snipeit_asset_id,
            'asset_tag' => $item->asset_tag,
            'serial' => $item->serial,
            'status' => 'Match',
            'physical_location' => 'F1',
            'physical_user' => 'Available',
        ])->assertOk();

        $this->assertDatabaseHas('action_logs', [
            'user_id' => $user->id,
            'action_type' => 'audit_verified',
            'item_type' => AuditItem::class,
            'item_id' => $item->id,
            'target_id' => $session->id,
            'snipeit_id' => $item->snipeit_asset_id,
        ]);
    }
}
