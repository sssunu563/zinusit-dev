<?php

namespace Tests\Feature;

use App\Models\Stb;
use App\Models\User;
use App\Services\SnipeItService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StbTest extends TestCase
{
    use RefreshDatabase;

    private function createStb(): Stb
    {
        return Stb::create([
            'deliver_date' => now()->toDateString(),
            'status' => 1,
            'document_type' => 'handover',
            'movement_type' => 'out',
            'it_drafter_id' => 10,
            'it_checker_id' => 11,
            'it_approved_id' => 12,
            'req_doc_no' => 'REQ-001',
            'po_doc_no' => 'PO-001',
            'user_id' => 99,
            'group_id' => 100,
            'building' => 'Building A',
            'remark' => 'Remark',
        ]);
    }

    private function markAllSignatures(Stb $stb): void
    {
        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9pJzi10AAAAASUVORK5CYII=';

        foreach ([
            'it_drafter',
            'it_checker',
            'it_approved',
            'requester_received',
            'requester_dept_head',
        ] as $role) {
            $stb->forceFill([
                "{$role}_signature_path" => $signature,
                "{$role}_signed_at" => now(),
            ])->save();
        }
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('stb.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_stb_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('stb.index'));
        $response->assertOk();
    }

    public function test_authenticated_users_can_visit_stb_create_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('stb.create'));
        $response->assertOk();
    }

    public function test_handover_create_defaults_to_out_movement_type_and_current_user_drafter()
    {
        $user = User::factory()->create([
            'snipeit_user_id' => 42,
        ]);
        $this->actingAs($user);

        $response = $this->get(route('stb.create', [
            'documentType' => 'handover',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stb/Create')
            ->where('initialData.documentType', 'handover')
            ->where('initialData.movementType', 'out')
            ->where('initialData.itDrafter_id', 42)
        );
    }

    public function test_selected_asset_ids_are_preloaded_for_quick_stb_creation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app()->instance(SnipeItService::class, new class extends SnipeItService {
            public function getHardware(?int $id): ?array
            {
                return [
                    'id' => $id,
                    'name' => 'Laptop Test ' . $id,
                    'serial' => 'SN-' . $id,
                    'asset_tag' => 'TAG-' . $id,
                    'category' => ['name' => 'Hardware'],
                    'model' => ['name' => 'ThinkPad'],
                    'status_label' => ['name' => 'Active'],
                ];
            }
        });

        $response = $this->get(route('stb.create', [
            'documentType' => 'handover',
            'movementType' => 'out',
            'selectedAssetIds' => [101, 102],
        ]));

        $response->assertOk();
        $response->assertSee('Laptop Test 101');
        $response->assertSee('Laptop Test 102');
    }

    public function test_selected_asset_ids_autofill_user_for_quick_stb_in_creation()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        app()->instance(SnipeItService::class, new class extends SnipeItService {
            public function getHardware(?int $id): ?array
            {
                return [
                    'id' => $id,
                    'name' => 'Laptop Test ' . $id,
                    'serial' => 'SN-' . $id,
                    'asset_tag' => 'TAG-' . $id,
                    'category' => ['name' => 'Hardware'],
                    'assigned_to' => ['id' => 77],
                    'status_label' => ['name' => 'Active'],
                ];
            }

            public function getUser(?int $id): ?array
            {
                if ($id !== 77) {
                    return null;
                }

                return [
                    'id' => 77,
                    'name' => 'User 77',
                    'location_id' => 88,
                ];
            }
        });

        $response = $this->get(route('stb.create', [
            'documentType' => 'handover',
            'movementType' => 'return',
            'selectedAssetIds' => [101, 102],
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Stb/Create')
            ->where('initialData.user_id', 77)
            ->where('initialData.group_id', 88)
        );
    }

    public function test_authenticated_users_can_visit_stb_show_page()
    {
        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);

        $response = $this->get(route('stb.show', $stb));
        $response->assertOk();
    }

    public function test_authenticated_users_can_visit_stb_edit_page()
    {
        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);

        $response = $this->get(route('stb.edit', $stb));
        $response->assertOk();
    }

    public function test_authenticated_users_can_visit_stb_print_page()
    {
        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);

        $response = $this->get(route('stb.print', $stb));
        $response->assertOk();
    }

    public function test_authenticated_users_can_store_stb()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        app()->instance(SnipeItService::class, new class extends SnipeItService {
            public function getHardware(?int $id): ?array
            {
                return [
                    'id' => $id,
                    'name' => 'Laptop',
                    'serial' => 'SN-001',
                    'asset_tag' => 'TAG-001',
                    'category' => ['name' => 'Hardware'],
                    'status_label' => ['name' => 'Stock'],
                ];
            }
        });

        $response = $this->post(route('stb.store'), [
            'docId' => 'STB-ZGI-2603-00001',
            'id' => 1,
            'deliverDate' => now()->toDateString(),
            'documentType' => 'handover',
            'movementType' => 'out',
            'itDrafter_id' => 10,
            'itChecker_id' => 11,
            'itApproved_id' => 12,
            'reqDocNo' => 'REQ-002',
            'poDocNo' => 'PO-002',
            'user_id' => 99,
            'group_id' => 100,
            'building' => 'Building B',
            'useDate' => now()->toDateString(),
            'batchNo' => 'BATCH-01',
            'photo' => UploadedFile::fake()->image('stb.jpg'),
            'remark' => 'Created from test',
            'createDate' => now()->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'nama' => 'Laptop',
                    'type' => 'Device',
                    'jumlah' => 1,
                    'serialNo' => 'SN-001',
                    'computer_id' => 501,
                ],
            ],
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('stbs', [
            'req_doc_no' => 'REQ-002',
            'po_doc_no' => 'PO-002',
            'user_id' => 99,
            'group_id' => 100,
            'batch_no' => 'BATCH-01',
            'document_type' => 'handover',
            'movement_type' => 'out',
        ]);
        $this->assertDatabaseHas('stb_items', [
            'nama' => 'Laptop',
            'serial_no' => 'SN-001',
            'computer_id' => 501,
        ]);

        $storedStb = Stb::query()->where('req_doc_no', 'REQ-002')->firstOrFail();
        $this->assertNotNull($storedStb->photo);
        $this->assertSame('stb-photos/STB-ZGI-2603-00001.jpg', $storedStb->photo);
    }

    public function test_storage_photo_path_is_normalized_for_browser_rendering()
    {
        Storage::fake('public');
        $path = 'stb-photos/STB-ZGI-2603-00002.jpg';
        $file = UploadedFile::fake()->image('STB-ZGI-2603-00002.jpg');
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $controller = app(\App\Http\Controllers\StbController::class);
        $method = new \ReflectionMethod($controller, 'resolveStorageDataUri');
        $method->setAccessible(true);

        $this->assertNotNull($method->invoke($controller, $path));
        $this->assertNotNull($method->invoke($controller, 'public/' . $path));
        $this->assertNotNull($method->invoke($controller, '/storage/' . $path));
    }

    public function test_authenticated_users_can_sign_stb()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);

        $signature = 'data:image/png;base64,' . base64_encode(
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9pJzi10AAAAASUVORK5CYII=',
                true,
            ),
        );

        $response = $this->post(route('stb.sign', ['stb' => $stb, 'role' => 'it_drafter']), [
            'docId' => 'STB-ZGI-2603-00001',
            'signature' => $signature,
        ]);

        $response->assertRedirect(route('stb.show', $stb));

        $stb->refresh();

        $this->assertStringStartsWith('data:image/png;base64,', $stb->it_drafter_signature_path);
        $this->assertNotNull($stb->it_drafter_signed_at);
    }

    public function test_guest_can_open_signed_share_link()
    {
        $stb = $this->createStb();

        $url = URL::temporarySignedRoute('stb.share', now()->addDays(7), [
            'stb' => $stb->id,
        ]);

        $response = $this->get($url);

        $response->assertOk();
    }

    public function test_guest_can_sign_from_signed_share_link()
    {
        Storage::fake('public');

        $stb = $this->createStb();
        $signature = 'data:image/png;base64,' . base64_encode(
            base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9pJzi10AAAAASUVORK5CYII=',
                true,
            ),
        );

        $url = URL::temporarySignedRoute('stb.share.sign', now()->addDays(7), [
            'stb' => $stb->id,
            'role' => 'requester_received',
        ]);

        $response = $this->postJson($url, [
            'docId' => 'STB-ZGI-2603-00001',
            'signature' => $signature,
        ]);

        $response->assertOk();

        $stb->refresh();

        $this->assertStringStartsWith('data:image/png;base64,', $stb->requester_received_signature_path);
        $this->assertNotNull($stb->requester_received_signed_at);
    }

    public function test_authenticated_users_can_clear_stb_signature()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);

        Storage::disk('public')->put('stb-signatures/STB-ZGI-2603-00001-it_drafter.png', 'fake');
        $stb->update([
            'it_drafter_signature_path' => 'stb-signatures/STB-ZGI-2603-00001-it_drafter.png',
            'it_drafter_signed_at' => now(),
        ]);

        $response = $this->deleteJson(route('stb.sign.clear', ['stb' => $stb, 'role' => 'it_drafter']));

        $response->assertOk();

        $stb->refresh();

        $this->assertNull($stb->it_drafter_signature_path);
        $this->assertNull($stb->it_drafter_signed_at);
        Storage::disk('public')->assertMissing('stb-signatures/STB-ZGI-2603-00001-it_drafter.png');
    }

    public function test_authenticated_users_can_complete_stb_after_all_signatures_exist()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);
        $this->markAllSignatures($stb);

        $response = $this->postJson(route('stb.complete', $stb));

        $response->assertOk();

        $stb->refresh();

        $this->assertNotNull($stb->completed_at);
        $this->assertSame('stb-pdfs/STB-1.pdf', $stb->completed_pdf_path);
        Storage::disk('public')->assertExists('stb-pdfs/STB-1.pdf');
    }

    public function test_clearing_signature_resets_completed_state()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $stb = $this->createStb();
        $this->actingAs($user);
        $this->markAllSignatures($stb);

        Storage::disk('public')->put('stb-pdfs/stb-00001.pdf', '%PDF-1.4 test');
        $stb->update([
            'completed_pdf_path' => 'stb-pdfs/stb-00001.pdf',
            'completed_at' => now(),
        ]);

        $response = $this->deleteJson(route('stb.sign.clear', ['stb' => $stb, 'role' => 'it_drafter']));

        $response->assertOk();

        $stb->refresh();

        $this->assertNull($stb->completed_at);
        $this->assertNull($stb->completed_pdf_path);
        Storage::disk('public')->assertMissing('stb-pdfs/stb-00001.pdf');
    }

    public function test_stb_show_redirects_loan_document_to_peminjaman_module()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $loan = Stb::create([
            'deliver_date' => now()->toDateString(),
            'status' => 3,
            'document_type' => 'loan',
            'movement_type' => 'out',
            'user_id' => 99,
            'group_id' => 100,
        ]);

        $response = $this->get(route('stb.show', $loan));

        $response->assertRedirect(route('peminjaman.show', $loan));
    }

    public function test_stb_store_redirects_loan_payload_to_peminjaman_module()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $loanOut = Stb::create([
            'deliver_date' => now()->toDateString(),
            'status' => 3,
            'document_type' => 'loan',
            'movement_type' => 'out',
            'it_drafter_id' => 10,
            'it_checker_id' => 11,
            'it_approved_id' => 12,
            'user_id' => 99,
            'group_id' => 100,
        ]);

        $this->markAllSignatures($loanOut);

        app()->instance(SnipeItService::class, new class extends SnipeItService {
            public function getHardware(?int $id): ?array
            {
                return [
                    'id' => $id,
                    'name' => 'Laptop',
                    'serial' => 'SN-RET-001',
                    'asset_tag' => 'TAG-RET-001',
                    'category' => ['name' => 'Hardware'],
                    'status_label' => ['name' => 'Active'],
                ];
            }
        });

        $response = $this->post(route('stb.store'), [
            'deliverDate' => now()->toDateString(),
            'documentType' => 'loan',
            'movementType' => 'return',
            'linkedStbId' => $loanOut->id,
            'itDrafter_id' => 10,
            'itChecker_id' => 11,
            'itApproved_id' => 12,
            'user_id' => 99,
            'group_id' => 100,
            'building' => 'Building B',
            'useDate' => now()->toDateString(),
            'batchNo' => 'RET-01',
            'remark' => 'Pengembalian asset pinjaman',
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

        $response->assertRedirect(route('peminjaman.create', [
            'movementType' => 'return',
            'linkedStbId' => $loanOut->id,
        ]));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('stbs', 1);
    }
}

