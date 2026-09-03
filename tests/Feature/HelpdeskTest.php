<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpdeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_helpdesk_page(): void
    {
        $response = $this->get('/helpdesk');

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_non_super_admin_only_sees_own_helpdesk_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownTicket = Ticket::query()->create([
            'created_by' => $user->id,
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'priority' => 'High',
            'requester' => 'User A',
            'department' => 'IT',
            'issue_description' => 'Internet down',
            'action_taken' => 'Restart switch',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        Ticket::query()->create([
            'created_by' => $otherUser->id,
            'company' => 'Zinus',
            'location' => 'Karawang',
            'category' => 'Hardware',
            'priority' => 'Low',
            'requester' => 'User B',
            'department' => 'GA',
            'issue_description' => 'Mouse issue',
            'action_taken' => 'Replace mouse',
            'technician' => $otherUser->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-05',
        ]);

        $response = $this->actingAs($user)->get('/helpdesk');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Index')
            ->where('canViewAll', false)
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ownTicket->id)
            ->where('tickets.data.0.issue_description', 'Internet down'));
    }

    public function test_helpdesk_create_starts_with_blank_requester_company_location_and_department(): void
    {
        Cache::flush();

        $user = User::factory()->create([
            'snipeit_user_id' => 7001,
            'name' => 'Teknisi Lokal',
        ]);

        Cache::put('snipeit-user-profile:7001', [
            'name' => 'Teknisi Remote',
            'company' => ['name' => 'Zinus'],
            'location' => ['name' => 'Cikarang'],
            'department' => ['name' => 'IT Infrastructure'],
        ], now()->addMinutes(10));

        $response = $this->actingAs($user)->get('/helpdesk');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Index')
            ->where('initialValues.company', '')
            ->where('initialValues.location', '')
            ->where('initialValues.department', '')
            ->where('initialValues.note', '')
            ->where('initialValues.requester', '')
            ->where('initialValues.snipeit_asset_id', null)
            ->where('initialValues.asset_reference_snapshot', '')
            ->where('initialValues.ticket_scope', 'general')
            ->where('initialValues.maintenance_type', 'Pemeliharaan')
            ->where('initialValues.technician', 'Teknisi Lokal'));
    }

    public function test_non_asset_helpdesk_can_save_locally_without_snipeit_sync(): void
    {
        Config::set('services.snipeit.url', 'https://snipeit.test');
        Config::set('services.snipeit.token', 'token-test');

        Http::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/helpdesk', [
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'WiFi Support',
            'ticket_scope' => 'general',
            'priority' => 'Medium',
            'requester' => 'Budi Santoso',
            'department' => 'IT Infrastructure',
            'issue_description' => 'Tidak bisa konek ke wifi meeting room',
            'action_taken' => 'Reset access profile dan test reconnect',
            'note' => 'Tidak terkait perangkat asset tertentu',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $response->assertRedirect('/helpdesk');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'created_by' => $user->id,
            'category' => 'WiFi Support',
            'ticket_scope' => 'general',
            'snipeit_asset_id' => null,
            'snipeit_maintenance_id' => null,
            'snipeit_sync_status' => null,
        ]);

        Http::assertNothingSent();
    }

    public function test_asset_helpdesk_requires_snipeit_asset_selection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/helpdesk', [
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Laptop',
            'ticket_scope' => 'asset',
            'priority' => 'High',
            'requester' => 'Budi Santoso',
            'department' => 'IT Infrastructure',
            'issue_description' => 'Laptop tidak menyala',
            'action_taken' => 'Pengecekan awal',
            'note' => 'Asset belum dipilih',
            'maintenance_type' => 'Repair',
            'technician' => $user->name,
            'status' => 'Open',
            'date_closed' => '',
        ]);

        $response->assertSessionHasErrors(['snipeit_asset_id']);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_helpdesk_can_sync_selected_asset_to_snipeit_maintenance(): void
    {
        Config::set('services.snipeit.url', 'https://snipeit.test');
        Config::set('services.snipeit.token', 'token-test');

        Http::fake([
            'https://snipeit.test/api/v1/hardware/123' => Http::response([
                'id' => 123,
                'name' => 'Laptop User A',
                'asset_tag' => 'AST-123',
                'supplier' => ['id' => 45, 'name' => 'Vendor A'],
            ], 200),
            'https://snipeit.test/api/v1/maintenances' => Http::response([
                'status' => 'success',
                'payload' => ['id' => 9981],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/helpdesk', [
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Hardware',
            'ticket_scope' => 'asset',
            'priority' => 'High',
            'requester' => 'Budi Santoso',
            'department' => 'IT Infrastructure',
            'snipeit_asset_id' => 123,
            'asset_reference_snapshot' => 'AST-123',
            'maintenance_type' => 'Repair',
            'issue_description' => 'Laptop tidak bisa booting',
            'action_taken' => 'Diagnosa storage dan reinstall',
            'note' => 'Butuh observasi 1 hari',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $response->assertRedirect('/helpdesk');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'created_by' => $user->id,
            'snipeit_asset_id' => 123,
            'asset_reference_snapshot' => 'AST-123',
            'maintenance_type' => 'Repair',
            'snipeit_maintenance_id' => 9981,
            'snipeit_sync_status' => 'synced',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://snipeit.test/api/v1/maintenances'
                && $request->method() === 'POST'
                && $request['asset_id'] === 123
                && $request['supplier_id'] === 45
                && $request['asset_maintenance_type'] === 'Repair';
        });
    }

    public function test_helpdesk_still_saves_locally_when_snipeit_sync_fails(): void
    {
        Config::set('services.snipeit.url', 'https://snipeit.test');
        Config::set('services.snipeit.token', 'token-test');

        Http::fake([
            'https://snipeit.test/api/v1/hardware/124' => Http::response([
                'id' => 124,
                'name' => 'Laptop User B',
                'asset_tag' => 'AST-124',
            ], 200),
            'https://snipeit.test/api/v1/suppliers*' => Http::response([], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/helpdesk', [
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Hardware',
            'ticket_scope' => 'asset',
            'priority' => 'Medium',
            'requester' => 'Sari',
            'department' => 'IT Support',
            'snipeit_asset_id' => 124,
            'asset_reference_snapshot' => 'AST-124',
            'maintenance_type' => 'Maintenance',
            'issue_description' => 'Blue screen',
            'action_taken' => 'Cek driver',
            'note' => 'Belum ada supplier di asset',
            'technician' => $user->name,
            'status' => 'In Progress',
            'date_closed' => '',
        ]);

        $response->assertRedirect('/helpdesk');
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('tickets', [
            'created_by' => $user->id,
            'snipeit_asset_id' => 124,
            'asset_reference_snapshot' => 'AST-124',
            'snipeit_sync_status' => 'failed',
        ]);
        $this->assertDatabaseMissing('tickets', [
            'created_by' => $user->id,
            'snipeit_maintenance_id' => 9981,
        ]);
    }

    public function test_authenticated_user_can_view_own_helpdesk_detail(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::query()->create([
            'created_by' => $user->id,
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'priority' => 'High',
            'requester' => 'User A',
            'department' => 'IT',
            'issue_description' => 'Internet down',
            'action_taken' => 'Restart switch',
            'note' => 'Butuh monitoring ulang setelah jam makan siang',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $response = $this->actingAs($user)->get('/helpdesk/' . $ticket->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Show')
            ->where('ticket.id', $ticket->id)
            ->where('ticket.issue_description', 'Internet down')
            ->where('ticket.note', 'Butuh monitoring ulang setelah jam makan siang'));
    }

    public function test_authenticated_user_can_open_own_helpdesk_print_page(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::query()->create([
            'created_by' => $user->id,
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'priority' => 'High',
            'requester' => 'User A',
            'department' => 'IT',
            'issue_description' => 'Internet down',
            'action_taken' => 'Restart switch',
            'note' => 'Print note',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $response = $this->actingAs($user)->get('/helpdesk/' . $ticket->id . '/print');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Print')
            ->where('ticket.id', $ticket->id)
            ->where('ticket.action_taken', 'Restart switch')
            ->where('ticket.note', 'Print note'));
    }

    public function test_authenticated_user_cannot_view_other_users_helpdesk_detail(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ticket = Ticket::query()->create([
            'created_by' => $otherUser->id,
            'company' => 'Zinus',
            'location' => 'Karawang',
            'category' => 'Hardware',
            'priority' => 'Low',
            'requester' => 'User B',
            'department' => 'GA',
            'issue_description' => 'Mouse issue',
            'action_taken' => 'Replace mouse',
            'technician' => $otherUser->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-05',
        ]);

        $response = $this->actingAs($user)->get('/helpdesk/' . $ticket->id);

        $response->assertForbidden();
    }

    public function test_authenticated_user_cannot_open_other_users_helpdesk_print_page(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ticket = Ticket::query()->create([
            'created_by' => $otherUser->id,
            'company' => 'Zinus',
            'location' => 'Karawang',
            'category' => 'Hardware',
            'priority' => 'Low',
            'requester' => 'User B',
            'department' => 'GA',
            'issue_description' => 'Mouse issue',
            'action_taken' => 'Replace mouse',
            'technician' => $otherUser->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-05',
        ]);

        $response = $this->actingAs($user)->get('/helpdesk/' . $ticket->id . '/print');

        $response->assertForbidden();
    }

    public function test_storing_open_helpdesk_activity_clears_date_closed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/helpdesk', [
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'ticket_scope' => 'general',
            'priority' => 'Medium',
            'requester' => 'User A',
            'department' => 'IT',
            'issue_description' => 'Issue baru',
            'action_taken' => 'Masih investigasi',
            'note' => 'Follow up besok pagi',
            'technician' => $user->name,
            'status' => 'Open',
            'date_closed' => '2026-04-06',
        ]);

        $response->assertRedirect('/helpdesk');
        $this->assertDatabaseHas('tickets', [
            'created_by' => $user->id,
            'status' => 'Open',
            'note' => 'Follow up besok pagi',
            'date_closed' => null,
        ]);
    }

    public function test_helpdesk_persists_requester_and_department_as_labels_not_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/helpdesk', [
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'ticket_scope' => 'general',
            'priority' => 'Medium',
            'requester' => 'Budi Santoso',
            'department' => 'IT Infrastructure',
            'issue_description' => 'Access point restart',
            'action_taken' => 'Power cycle dan cek uplink',
            'note' => 'Requester dipilih dari dropdown Snipe-IT',
            'maintenance_type' => 'Maintenance',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $response->assertRedirect('/helpdesk');
        $this->assertDatabaseHas('tickets', [
            'created_by' => $user->id,
            'requester' => 'Budi Santoso',
            'department' => 'IT Infrastructure',
        ]);
        $this->assertDatabaseMissing('tickets', [
            'created_by' => $user->id,
            'requester' => '123',
        ]);
    }

    public function test_authenticated_users_can_export_their_helpdesk_records_as_excel(): void
    {
        $user = User::factory()->create();

        Ticket::query()->create([
            'created_by' => $user->id,
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'priority' => 'High',
            'requester' => 'User A',
            'department' => 'IT',
            'issue_description' => 'Internet down',
            'action_taken' => 'Restart switch',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $response = $this->actingAs($user)->get('/helpdesk/export?status=Closed');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->assertHeader('content-disposition');
    }

    public function test_super_admin_snipeit_user_can_view_all_helpdesk_records(): void
    {
        Cache::flush();

        $superAdmin = User::factory()->create([
            'snipeit_user_id' => 9001,
        ]);
        $otherUser = User::factory()->create();

        Ticket::query()->create([
            'created_by' => $superAdmin->id,
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'priority' => 'High',
            'requester' => 'Admin User',
            'department' => 'IT',
            'issue_description' => 'Router down',
            'action_taken' => 'Reconfigure router',
            'technician' => $superAdmin->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        $otherTicket = Ticket::query()->create([
            'created_by' => $otherUser->id,
            'company' => 'Zinus',
            'location' => 'Karawang',
            'category' => 'Hardware',
            'priority' => 'Medium',
            'requester' => 'Staff User',
            'department' => 'GA',
            'issue_description' => 'Keyboard broken',
            'action_taken' => 'Replace keyboard',
            'technician' => $otherUser->name,
            'maintenance_type' => 'Maintenance',
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        Cache::put('snipeit-user-superadmin:9001', [
            'permissions' => [
                'superuser' => true,
            ],
        ], now()->addMinutes(10));

        $response = $this->actingAs($superAdmin)->get('/helpdesk');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Helpdesk/Index')
            ->where('canViewAll', true)
            ->has('tickets.data', 2)
            ->where('tickets.data.0.id', $otherTicket->id));
    }

    public function test_grafana_api_returns_all_helpdesk_records_with_valid_api_key(): void
    {
        Config::set('services.grafana.api_key', 'grafana-secret');

        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Ticket::query()->create([
            'created_by' => $user->id,
            'company' => 'Zinus',
            'location' => 'Cikarang',
            'category' => 'Network',
            'priority' => 'High',
            'requester' => 'User A',
            'department' => 'IT',
            'issue_description' => 'Internet down',
            'action_taken' => 'Restart switch',
            'technician' => $user->name,
            'status' => 'Closed',
            'date_closed' => '2026-04-06',
        ]);

        Ticket::query()->create([
            'created_by' => $otherUser->id,
            'company' => 'Zinus',
            'location' => 'Karawang',
            'category' => 'Hardware',
            'priority' => 'Low',
            'requester' => 'User B',
            'department' => 'GA',
            'issue_description' => 'Mouse issue',
            'action_taken' => 'Replace mouse',
            'technician' => $otherUser->name,
            'status' => 'Open',
            'date_closed' => null,
        ]);

        $response = $this->getJson('/api/helpdesk?api_key=grafana-secret');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 2);
        $response->assertJsonCount(2, 'data');
    }

    public function test_grafana_api_rejects_invalid_api_key(): void
    {
        Config::set('services.grafana.api_key', 'grafana-secret');

        $response = $this->getJson('/api/helpdesk?api_key=wrong-key');

        $response->assertForbidden();
    }
}