<?php

namespace Tests\Feature;

use App\Models\Stb;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PeminjamanTest extends TestCase
{
    use RefreshDatabase;

    private function markAllSignatures(Stb $stb): void
    {
        foreach ([
            'it_drafter',
            'it_checker',
            'it_approved',
            'requester_received',
            'requester_dept_head',
        ] as $role) {
            $path = "stb-signatures/STB-ZGI-2603-00001-{$role}.png";
            Storage::disk('public')->put($path, 'fake');

            $stb->forceFill([
                "{$role}_signature_path" => $path,
                "{$role}_signed_at" => now(),
            ])->save();
        }
    }

    public function test_guests_are_redirected_from_peminjaman_index()
    {
        $response = $this->get(route('peminjaman.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_peminjaman_index()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('peminjaman.index'));

        $response->assertOk();
    }

    public function test_completing_loan_return_marks_the_linked_loan_as_returned()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        $loanOut = \App\Models\Peminjaman::create([
            'document_type' => 'loan',
            'movement_type' => 'out',
            'status' => 3,
            'user_id' => 99,
            'group_id' => 100,
            'user_name' => 'Test User',
            'location_name' => 'ZGI BGR F1',
            'it_drafter_id' => 10,
            'it_drafter_signature_path' => 'loan-signature.png',
            'it_drafter_signed_at' => now(),
            'requester_received_signature_path' => 'requester-signature.png',
            'requester_received_signed_at' => now(),
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->postJson(route('peminjaman.complete', $loanOut));

        $response->assertOk();

        $loanOut->refresh();
        $this->assertNotNull($loanOut->completed_at);
    }

    public function test_return_request_redirects_to_original_loan_without_creating_new_document()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        app()->instance(\App\Services\SnipeItService::class, new class extends \App\Services\SnipeItService {
            public function getHardware(?int $id): ?array
            {
                if ($id === null) {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => 'Laptop',
                    'serial' => 'SN-RET-001',
                    'asset_tag' => 'TAG-RET-001',
                    'category' => ['name' => 'Hardware'],
                    'status_label' => ['name' => 'Borrow'],
                    'assigned_to' => ['name' => 'Test User'],
                ];
            }
        });

        $loanOut = \App\Models\Peminjaman::create([
            'document_type' => 'loan',
            'movement_type' => 'out',
            'status' => 3,
            'user_id' => 99,
            'group_id' => 100,
            'user_name' => 'Test User',
            'location_name' => 'ZGI BGR F1',
            'it_drafter_id' => 10,
            'it_drafter_signature_path' => 'loan-signature.png',
            'it_drafter_signed_at' => now(),
            'requester_received_signature_path' => 'requester-signature.png',
            'requester_received_signed_at' => now(),
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $response = $this->post(route('peminjaman.store'), [
            'movementType' => 'return',
            'linkedLoanId' => $loanOut->id,
            'user_id' => 99,
            'group_id' => 100,
            'itDrafter_id' => 10,
            'useDate' => now()->toDateString(),
            'remark' => 'Pencatatan pengembalian',
            'createDate' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'nama' => 'Laptop',
                    'type' => 'Device',
                    'kategori' => 'assets',
                    'jumlah' => 1,
                    'serialNo' => 'SN-RET-001',
                    'computer_id' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('peminjaman.show', $loanOut));

        $this->assertDatabaseCount('peminjamans', 1);
    }

    public function test_loan_store_accepts_hardware_item_with_only_snipeit_asset_id()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('peminjaman.store'), [
            'movementType' => 'out',
            'user_id' => 99,
            'group_id' => 100,
            'useDate' => now()->toDateString(),
            'remark' => 'Pengajuan pinjaman tanpa computer_id manual',
            'createDate' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'nama' => 'Laptop',
                    'type' => 'Device',
                    'kategori' => 'assets',
                    'jumlah' => 1,
                    'serialNo' => 'SN-001',
                    'inventory_number' => 'INV-001',
                    'snipeit_asset_id' => 101,
                    'condition' => 'Good',
                ],
            ],
        ]);

        $response->assertRedirect();
    }

    public function test_return_creation_redirects_to_original_loan_instead_of_creating_new_return_document()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $loan = \App\Models\Peminjaman::create([
            'document_type' => 'loan',
            'movement_type' => 'out',
            'status' => 3,
            'user_id' => 99,
            'group_id' => 100,
            'user_name' => 'Budi',
            'location_name' => 'ZGI BGR F1',
            'is_completed' => true,
            'completed_at' => now(),
            'it_drafter_id' => 10,
        ]);

        $response = $this->get(route('peminjaman.create', [
            'movementType' => 'return',
            'linkedLoanId' => $loan->id,
        ]));

        $response->assertRedirect(route('peminjaman.show', $loan));

        $this->assertDatabaseMissing('peminjamans', [
            'linked_stb_id' => $loan->id,
            'movement_type' => 'return',
        ]);
    }

    public function test_selected_asset_ids_are_preloaded_for_quick_peminjaman_creation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app()->instance(\App\Services\SnipeItService::class, new class extends \App\Services\SnipeItService {
            public function getHardware(?int $id): ?array
            {
                if ($id === null) {
                    return null;
                }

                return [
                    'id' => $id,
                    'name' => 'Laptop Test ' . $id,
                    'serial' => 'SN-' . $id,
                    'asset_tag' => 'TAG-' . $id,
                    'category' => ['name' => 'Hardware'],
                    'status_label' => ['name' => 'Stock'],
                ];
            }
        });

        $response = $this->get(route('peminjaman.create', [
            'movementType' => 'out',
            'selectedAssetIds' => [201, 202],
        ]));

        $response->assertOk();
        $response->assertSee('Laptop Test 201');
        $response->assertSee('Laptop Test 202');
    }
}