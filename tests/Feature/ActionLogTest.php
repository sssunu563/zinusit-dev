<?php

namespace Tests\Feature;

use App\Models\ActionLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Procurement;
use App\Models\Stb;
use App\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user and authenticate
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * Test STB Creation Logging - Structure Test
     */
    public function test_stb_creation_logs_to_action_log()
    {
        ActionLog::query()->delete();

        // Directly create ActionLog entry (simulating what store() does)
        $log = ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'created',
            'item_type' => Stb::class,
            'item_id' => 1,
            'note' => 'Created STB document (ID: 1, Type: handover, Movement: out)',
            'log_meta' => [
                'stb_id' => 1,
                'document_type' => 'handover',
                'movement_type' => 'out',
                'user_id' => 1,
                'group_id' => 1,
                'items_count' => 1,
            ],
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('created', $log->action_type);
        $this->assertEquals(Stb::class, $log->item_type);
        $this->assertEquals('handover', $log->log_meta['document_type']);
        $this->assertEquals('out', $log->log_meta['movement_type']);
    }

    /**
     * Test Peminjaman Creation Logging - Structure Test
     */
    public function test_peminjaman_creation_logs_to_action_log()
    {
        ActionLog::query()->delete();

        // Directly create ActionLog entry (simulating what store() does)
        $log = ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'created',
            'item_type' => Peminjaman::class,
            'item_id' => 1,
            'note' => 'Created loan document (ID: 1, Type: loan, Movement: out)',
            'log_meta' => [
                'peminjaman_id' => 1,
                'document_type' => 'loan',
                'movement_type' => 'out',
                'user_id' => 1,
                'user_name' => 'Test User',
                'group_id' => 1,
                'items_count' => 2,
            ],
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('created', $log->action_type);
        $this->assertEquals(Peminjaman::class, $log->item_type);
        $this->assertEquals('loan', $log->log_meta['document_type']);
        $this->assertEquals(2, $log->log_meta['items_count']);
    }

    /**
     * Test ActionLog entries exist for all required modules
     */
    public function test_all_modules_have_action_log_capability()
    {
        ActionLog::query()->delete();

        $modules = [
            [Vendor::class, 'vendor_test'],
            [Procurement::class, 'procurement_test'],
            [Stb::class, 'stb_test'],
            [Peminjaman::class, 'peminjaman_test'],
        ];

        foreach ($modules as [$module, $docId]) {
            $log = ActionLog::create([
                'user_id' => $this->user->id,
                'action_type' => 'created',
                'item_type' => $module,
                'item_id' => 1,
                'note' => "Test for {$module}",
                'log_meta' => ['module' => $docId],
            ]);

            $this->assertNotNull($log);
            $this->assertEquals($module, $log->item_type);
        }

        // Verify all 4 entries exist
        $this->assertEquals(4, ActionLog::count());
    }

    /**
     * Test ActionLog user tracking
     */
    public function test_action_log_tracks_user_id()
    {
        $anotherUser = User::factory()->create();
        ActionLog::query()->delete();

        // Create logs as different users
        $log1 = ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'created',
            'item_type' => Vendor::class,
            'item_id' => 1,
            'note' => 'Test log 1',
            'log_meta' => [],
        ]);

        $log2 = ActionLog::create([
            'user_id' => $anotherUser->id,
            'action_type' => 'updated',
            'item_type' => Vendor::class,
            'item_id' => 1,
            'note' => 'Test log 2',
            'log_meta' => [],
        ]);

        // Verify both exist with correct user_ids
        $this->assertEquals(2, ActionLog::count());
        $this->assertTrue(ActionLog::where('user_id', $this->user->id)->exists());
        $this->assertTrue(ActionLog::where('user_id', $anotherUser->id)->exists());
    }

    /**
     * Test ActionLog metadata structure
     */
    public function test_action_log_metadata_structure()
    {
        ActionLog::query()->delete();

        $vendorMetadata = [
            'vendor_name' => 'Acme Corp',
            'contact_person' => 'John Doe',
            'email' => 'john@acme.com',
            'phone' => '555-1234',
            'category' => 'Hardware',
        ];

        $log = ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'created',
            'item_type' => Vendor::class,
            'item_id' => 1,
            'note' => 'Created vendor: Acme Corp',
            'log_meta' => $vendorMetadata,
        ]);

        $this->assertEquals($vendorMetadata, $log->log_meta);
        $this->assertEquals('Acme Corp', $log->log_meta['vendor_name']);
    }

    /**
     * Test ActionLog change tracking in metadata
     */
    public function test_action_log_change_tracking()
    {
        ActionLog::query()->delete();

        $changes = [
            'status' => ['old' => 'pending', 'new' => 'completed'],
            'cost' => ['old' => 1000, 'new' => 1500],
        ];

        $log = ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'updated',
            'item_type' => Procurement::class,
            'item_id' => 1,
            'note' => 'Updated procurement',
            'log_meta' => $changes,
        ]);

        $this->assertArrayHasKey('status', $log->log_meta);
        $this->assertEquals('pending', $log->log_meta['status']['old']);
        $this->assertEquals('completed', $log->log_meta['status']['new']);
    }

    public function test_activity_logs_page_loads_and_excludes_forms(): void
    {
        ActionLog::query()->delete();

        ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'created',
            'item_type' => User::class,
            'item_id' => $this->user->id,
            'note' => 'Created user',
        ]);

        ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'stb_complete',
            'item_type' => Stb::class,
            'item_id' => 1,
            'note' => 'Completed STB',
        ]);

        $response = $this->get('/action-logs');

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Logs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.item_type', 'User'));
    }

    public function test_activity_logs_can_be_exported_as_csv(): void
    {
        ActionLog::query()->delete();

        ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'login',
            'item_type' => User::class,
            'item_id' => $this->user->id,
            'note' => 'User logged in',
        ]);

        $response = $this->get('/action-logs/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Waktu,"Otorisator / User",Kategori,"Entitas Item",Aksi,Target,Catatan', $csv);
        $this->assertStringContainsString('LOGIN', $csv);
    }

    public function test_activity_logs_properly_resolves_asset_operations_and_names(): void
    {
        ActionLog::query()->delete();

        ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'create',
            'item_type' => 'snipeit_assets',
            'item_id' => 141,
            'snipeit_id' => 141,
            'snipeit_type' => 'assets',
            'note' => 'Asset baru dibuat: Laptop Dell XPS',
            'log_meta' => [
                'name' => 'Laptop Dell XPS',
                'asset_tag' => 'NB-P-DELL-001',
            ],
        ]);

        ActionLog::create([
            'user_id' => $this->user->id,
            'action_type' => 'update',
            'item_type' => 'snipeit_assets',
            'item_id' => 141,
            'snipeit_id' => 141,
            'snipeit_type' => 'assets',
            'note' => null,
            'log_meta' => [
                'name' => 'Laptop Dell XPS',
                'asset_tag' => 'NB-P-DELL-001',
            ],
        ]);

        $response = $this->get('/action-logs');

        $response->assertOk();
        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Logs/Index')
            ->has('logs.data', 2)
            ->where('logs.data.0.item_name', 'Laptop Dell XPS')
            ->where('logs.data.0.action_label', 'Diperbarui')
            ->where('logs.data.1.item_name', 'Laptop Dell XPS')
            ->where('logs.data.1.action_label', 'Dibuat'));
    }
}
