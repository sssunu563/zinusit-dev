<?php

namespace App\Traits;

use App\Models\Stb;
use App\Services\AssetNoteFormatterService;
use Illuminate\Support\Facades\Log;

trait DocumentCheckoutTrait
{
    /**
     * Trigger automated checkout to Snipe-IT for all items in a document.
     */
    protected function processSnipeItCheckout(mixed $document): void
    {
        // Document user_id stores the Snipe-IT user ID because the forms pull directly from Snipe-IT users.
        $snipeUserId = $document->user_id;

        if (!$snipeUserId) {
            Log::warning('Snipe-IT Checkout: Snipe-IT User ID is missing in document record.', [
                'doc_id' => $document->id,
                'user_name' => $document->user_name ?? 'Unknown'
            ]);
            return;
        }

        $userId = $snipeUserId;

        Log::info('Snipe-IT Checkout Triggered', [
            'doc_id' => $document->id,
            'user_id' => $userId,
            'item_count' => $document->items->count(),
        ]);

        $errors = [];
        $document->items->each(function ($item) use ($userId, $document, &$errors) {
            try {
                $category = strtolower(trim((string) ($item->kategori ?: 'assets')));
                
                // Skip manual items (no Snipe-IT checkout)
                if ($category === 'manual') {
                    Log::info("Snipe-IT Checkout skipped: Item '{$item->nama}' is a manual item (no Snipe-IT sync).", [
                        'doc_id' => $document->id,
                        'item_id' => $item->id,
                    ]);
                    return;
                }
                
                $assetId = $item->snipeit_asset_id;

                if (!$assetId) {
                    Log::warning("Snipe-IT Checkout skipped: Item '{$item->nama}' (ID: {$item->id}) is missing Snipe-IT asset ID.", [
                        'doc_id' => $document->id,
                        'item_id' => $item->id,
                    ]);
                    return;
                }

                $resource = match ($category) {
                    'assets', 'laptop', 'hardware', 'hardware_assets', 'custom_asset' => 'hardware',
                    'license', 'licenses'                                  => 'licenses',
                    'accessories', 'accessory'                              => 'accessories',
                    'component', 'components'                               => 'components',
                    'consumable', 'consumables'                             => 'consumables',
                    default                                                 => null,
                };

                $isStbDocument = $document instanceof \App\Models\Stb;

                if ($resource === 'components' && !$isStbDocument) {
                    throw new \Exception("Component '{$item->nama}' tidak dapat dipinjamkan melalui Peminjaman. Gunakan Accessories.");
                }

                if (!$resource) return;

                $recipientName = $document->user_name ?? ($document->user ? $document->user->name : 'Unknown User');
                $documentName = $isStbDocument && method_exists($this, 'formatStbDocumentName')
                    ? $this->formatStbDocumentName($document)
                    : "Peminjaman_{$document->id}";
                $documentId = method_exists($this, 'formatDocId')
                    ? $this->formatDocId($document, $document->user_company ?? null)
                    : (string) $document->id;
                $remark = trim((string) ($document->remark ?? ''));
                $note = "{$documentName} | Doc ID: {$documentId} | Item: {$item->nama} | SN: " . ($item->serial_no ?: '-') . " | Assign: {$recipientName}";
                if ($remark !== '') {
                    $note .= " | Catatan: {$remark}";
                }
                
                // Add reference if it's not the user (e.g. if it was meant for a specific asset)
                if ($item->inventory_number && $item->inventory_number !== '-') {
                    $note .= " | Ref: {$item->inventory_number}";
                }

                $payload = [
                    'checkout_at' => now()->toDateString(),
                    'note'        => $note,
                ];

                // Build payload per resource type
                if ($resource === 'hardware') {
                    $payload['checkout_to_type'] = 'user';
                    $payload['assigned_user'] = $userId;
                    $payload['assigned_to'] = $userId; // Dual-field support for varying API versions
                    
                    // For Peminjaman (Loans), attempt to set status to 'Borrow' or 'Ready to Deploy'
                    if (data_get($document, 'document_type') === 'loan') {
                        $payload['status_id'] = $this->resolveSnipeStatusId('borrow');
                    } else {
                        $payload['status_id'] = 1; 
                    }

                    Log::info('Snipe-IT Hardware Checkout Attempt', [
                        'asset_id' => $assetId,
                        'payload' => $payload
                    ]);

                    $result = $this->snipe->checkoutAsset($resource, $assetId, $payload);
                } elseif (in_array($resource, ['accessories', 'licenses', 'components', 'consumables'])) {
                    if ($resource === 'licenses') {
                        // Logic khusus License: Cari seat kosong
                        $seats = $this->snipe->getLicenseSeats($assetId, true);
                        $availableSeat = null;
                        foreach ($seats as $seat) {
                            // Cek seat yang belum di-assign
                            // Snipe-IT API biasanya mengembalikan null atau 0 untuk seat kosong
                            if (empty($seat['assigned_user']) && empty($seat['assigned_to']) && empty($seat['assigned_asset']) && empty($seat['asset_id'])) {
                                $availableSeat = $seat;
                                break;
                            }
                        }

                        if (!$availableSeat) {
                            throw new \Exception("Tidak ada seat kosong yang tersedia untuk license ini.");
                        }

                        $seatPayload = [
                            'note' => $note,
                        ];

                        $seatPayload['assigned_to'] = $userId;

                        $result = $this->snipe->checkoutLicenseSeat($assetId, (int) $availableSeat['id'], $seatPayload);
                        if (in_array(($result['status'] ?? ''), ['error', 'failure'], true)) {
                            throw new \Exception("Gagal checkout license '{$item->nama}': " . json_encode($result['messages'] ?? $result));
                        }
                        $this->snipe->flushCacheForAsset('license', (int) $assetId);

                        $updatedSeat = collect($this->snipe->getLicenseSeats($assetId, true))
                            ->first(fn (array $seat) => (int) ($seat['id'] ?? 0) === (int) $availableSeat['id']);
                        $assignedUserId = data_get($updatedSeat, 'assigned_user.id')
                            ?: data_get($updatedSeat, 'assigned_user')
                            ?: data_get($updatedSeat, 'assigned_to.id')
                            ?: data_get($updatedSeat, 'assigned_to');
                        if (!$updatedSeat || (int) $assignedUserId !== (int) $userId) {
                            throw new \Exception("Checkout license '{$item->nama}' tidak terverifikasi: seat tidak ter-assign ke user {$userId}.");
                        }
                    } else {
                        // Accessories, Components, Consumables
                        // Snipe-IT API for these resources typically uses 'assigned_to' as the primary ID field
                        // for both users and assets, depending on 'checkout_to_type'.
                        // Force checkout to user only
                        $payload['checkout_to_type'] = 'user';
                        $payload['assigned_user']    = $userId;
                        $payload['assigned_to']      = $userId;

                        if ($resource === 'components' && $isStbDocument) {
                            $computerId = (int) ($item->computer_id ?? 0);
                            if ($computerId <= 0) {
                                throw new \Exception("Component '{$item->nama}' harus memiliki hardware asset tujuan.");
                            }

                            $payload['checkout_to_type'] = 'asset';
                            $payload['assigned_to'] = $computerId;
                            $payload['assigned_qty'] = max(1, (int) ($item->jumlah ?? 1));
                            unset($payload['assigned_user'], $payload['assigned_asset'], $payload['checkout_qty']);
                            $result = $this->snipe->checkoutAsset($resource, (int) $assetId, $payload);
                        } elseif ($resource === 'accessories') {
                            $payload['checkout_qty'] = max(1, (int) ($item->jumlah ?? 1));
                        } elseif ($resource === 'consumables') {
                            $payload['checkout_qty'] = max(1, (int) ($item->jumlah ?? 1));
                        }
                        
                        if ($resource !== 'components' || !$isStbDocument) {
                            $result = $this->snipe->checkoutAsset($resource, $assetId, $payload);
                        }
                    }
                }

                // Handle result if not already handled by License logic
                if (isset($result) && in_array(($result['status'] ?? ''), ['error', 'failure'])) {
                    Log::error('Snipe-IT Checkout Error Response', [
                        'doc_id' => $document->id,
                        'item_id' => $item->id,
                        'response' => $result
                    ]);
                    
                    $errorDetails = $result['messages'] ?? $result['payload'] ?? null;
                    $errorMsg = is_array($errorDetails)
                        ? json_encode($errorDetails)
                        : ($errorDetails ?: 'Unknown Snipe-IT error');
                    throw new \Exception("Gagal checkout item '{$item->nama}': {$errorMsg}");
                }

                if ($resource !== 'hardware' && $resource !== 'consumables') {
                    $this->recordStbStockHistory(
                        $document,
                        $item,
                        -max(1, (int) ($item->jumlah ?? 1)),
                        'STB Penyerahan',
                    );
                }

                Log::info('Snipe-IT Checkout Success', [
                    'doc_id' => $document->id,
                    'item_id' => $item->id,
                    'asset_id' => $assetId,
                    'status' => $result['status'] ?? 'unknown',
                ]);
            } catch (\Throwable $e) {
                Log::error('Snipe-IT Checkout Exception', [
                    'doc_id' => $document->id,
                    'item_id' => $item->id,
                    'message' => $e->getMessage(),
                ]);
                $errors[] = $e->getMessage();
            }
        });

        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }
    }

    /**
     * Trigger automated check-in to Snipe-IT for all items in an STB (Return/Pengembalian).
     * Called automatically on complete() when movement_type = 'return'.
     */
    protected function processSnipeItCheckin(mixed $document): void
    {
        Log::info('Snipe-IT Checkin Triggered', [
            'doc_id'     => $document->id,
            'item_count' => $document->items->count(),
            'user_id'    => $document->user_id,
        ]);
        // Collect all hardware asset IDs in this document to help identify attachments (accessories/components/licenses)
        $documentAssetIds = $document->items->filter(fn($i) => in_array($i->kategori, ['assets', 'hardware', 'hardware_assets']))
            ->pluck('snipeit_asset_id')->filter()->map(fn($id) => (int)$id)->toArray();
        $documentComputerIds = $document->items->pluck('computer_id')->filter()->map(fn($id) => (int)$id)->toArray();
        $allPotentialAssetIds = array_unique(array_merge($documentAssetIds, $documentComputerIds));

        // Sort items: Non-hardware first, hardware last to ensure attachments are checked in while asset still has owner context
        $sortedItems = $document->items->sortBy(function($item) {
            $cat = strtolower($item->kategori ?: 'assets');
            return in_array($cat, ['assets', 'hardware', 'hardware_assets']) ? 2 : 1;
        });

        $sortedItems->each(function ($item) use ($document, $allPotentialAssetIds) {
            $category = $item->kategori ?: 'assets';
            
            // Skip manual items (no Snipe-IT checkin)
            if (strtolower($category) === 'manual') {
                Log::info("Snipe-IT Checkin skipped: Item '{$item->nama}' is a manual item (no Snipe-IT sync).", [
                    'doc_id' => $document->id,
                    'item_id' => $item->id,
                ]);
                return;
            }
            
            $assetId  = (int) ($item->snipeit_asset_id ?? 0);

            if ($assetId <= 0) {
                Log::warning("Snipe-IT Checkin skipped: no snipeit_asset_id", [
                    'doc_id'    => $document->id,
                    'item_id'   => $item->id,
                    'item_nama' => $item->nama,
                ]);
                return;
            }

            // Map internal category to Snipe-IT API resource name
            $resource = match (strtolower($category)) {
                'assets', 'hardware', 'hardware_assets' => 'hardware',
                'license', 'licenses'                   => 'licenses',
                'accessories', 'accessory'              => 'accessories',
                'component', 'components'               => 'components',
                'consumable', 'consumables'             => 'consumables',
                default                                 => null,
            };

            if (!$resource || $resource === 'consumables') {
                Log::info("Snipe-IT Checkin skipped: category '{$category}' " . ($resource === 'consumables' ? "is a consumable" : "unmapped"), [
                    'doc_id'  => $document->id,
                    'item_id' => $item->id,
                ]);
                return;
            }

            $userName = $document->user_name ?? 'Unknown User';
            $sn       = $item->serial_no ?: '-';
            $documentIdDisplay = $document->id_display ?: "#{$document->id}";
            
            // Use standardized formatter for hardware return notes
            $itemCondition = $item->condition ?? 'Good';
            $note = AssetNoteFormatterService::formatConditionNote(
                $document,
                condition: $itemCondition,
                catatan: null
            );
            
            // Add item details to the note
            $note .= " | Item: {$item->nama} | SN: {$sn} | Dari: {$userName}";
            if (!empty($item->inventory_number) && $item->inventory_number !== '-') {
                $note .= " | Ref: {$item->inventory_number}";
            }

            // Payload base: only 'note' is universally accepted by Snipe-IT checkin
            $payload = ['note' => $note];

            // Hardware: resolve status based on condition
            if ($resource === 'hardware') {
                $payload['status_id'] = $this->resolveSnipeStatusId($itemCondition);
            }

            try {
                Log::info("Snipe-IT Checkin Attempt: {$resource} #{$assetId}", [
                    'doc_id'  => $document->id,
                    'item_id' => $item->id,
                    'payload' => $payload,
                ]);

                if ($resource === 'licenses') {
                    try {
                        $seats = $this->snipe->getLicenseSeats($assetId);
                        $targetSeat = null;
                        foreach ($seats as $seat) {
                            // Cari seat yang sedang di-assign ke user ini
                            $assignedUserId = data_get($seat, 'assigned_user.id') ?: data_get($seat, 'assigned_user');
                            if ($assignedUserId && (int) $assignedUserId === (int) $document->user_id) {
                                $targetSeat = $seat;
                                break;
                            }
                            
                            // Cek juga jika di-assign ke Asset (jika STB memiliki kaitan asset)
                            $assignedAssetId = data_get($seat, 'assigned_asset.id') ?: data_get($seat, 'assigned_asset') ?: data_get($seat, 'asset_id');
                            if ($assignedAssetId && in_array((int)$assignedAssetId, $allPotentialAssetIds)) {
                                $targetSeat = $seat;
                                break;
                            }
                        }

                        if ($targetSeat) {
                            $result = $this->snipe->checkinLicenseSeat($assetId, (int) $targetSeat['id'], ['note' => $payload['note']]);
                        } else {
                            Log::warning("Snipe-IT License Checkin skipped: No matching seat found for user {$document->user_id} or asset {$item->computer_id}", [
                                'doc_id' => $document->id,
                                'license_id' => $assetId
                            ]);
                            return;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Snipe-IT License Checkin Exception", [
                            'doc_id' => $document->id,
                            'license_id' => $assetId,
                            'message' => $e->getMessage()
                        ]);
                        return;
                    }
                } elseif ($resource === 'accessories') {
                    try {
                        $checkouts = $this->snipe->getAccessoryCheckouts($assetId, true);
                        $targetCheckoutIds = [];
                        foreach ($checkouts as $checkout) {
                            $assignedToId = data_get($checkout, 'assigned_to.id') ?: data_get($checkout, 'assigned_to');
                            
                            // Check if assigned to User
                            if ($assignedToId && (int) $assignedToId === (int) $document->user_id) {
                                $targetCheckoutIds[] = (int) $checkout['id'];
                                continue;
                            }

                            // Check if assigned to any Asset in this STB
                            if ($assignedToId && in_array((int)$assignedToId, $allPotentialAssetIds)) {
                                $targetCheckoutIds[] = (int) $checkout['id'];
                            }
                        }

                        if ($targetCheckoutIds !== []) {
                            $requestedQty = max(1, (int) ($item->jumlah ?? 1));
                            $checkoutIds = array_slice($targetCheckoutIds, 0, $requestedQty);
                            if (count($checkoutIds) < $requestedQty) {
                                throw new \Exception("Checkout accessory '{$item->nama}' tidak mencukupi untuk dikembalikan ({$requestedQty} diminta, " . count($checkoutIds) . ' ditemukan).');
                            }

                            foreach ($checkoutIds as $targetCheckoutId) {
                                $result = $this->snipe->checkinAsset($resource, $targetCheckoutId, $payload);
                                if (in_array(($result['status'] ?? ''), ['error', 'failure'])) {
                                    $errorMsg = is_array($result['messages'] ?? null)
                                        ? json_encode($result['messages'])
                                        : ($result['messages'] ?? 'Unknown Snipe-IT error');
                                    throw new \Exception("Gagal checkin accessory '{$item->nama}': {$errorMsg}");
                                }
                            }
                        } else {
                            Log::warning("Snipe-IT Accessory Checkin skipped: No matching checkout record found for user {$document->user_id}", [
                                'doc_id' => $document->id,
                                'accessory_id' => $assetId
                            ]);
                            return;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Snipe-IT Accessory Checkin Exception", [
                            'doc_id' => $document->id,
                            'accessory_id' => $assetId,
                            'message' => $e->getMessage()
                        ]);
                        return;
                    }
                } elseif ($resource === 'components') {
                    try {
                        $checkouts = $this->snipe->getComponentCheckouts($assetId, true);
                        $targetCheckoutIds = [];
                        foreach ($checkouts as $checkout) {
                            $assignedAssetId = data_get($checkout, 'assigned_asset.id')
                                ?: data_get($checkout, 'assigned_asset')
                                ?: data_get($checkout, 'assigned_to.id')
                                ?: data_get($checkout, 'assigned_to');
                            if ($assignedAssetId && in_array((int) $assignedAssetId, $allPotentialAssetIds, true)) {
                                $targetCheckoutIds[] = (int) ($checkout['id'] ?? 0);
                            }
                        }

                        if ($targetCheckoutIds === []) {
                            throw new \Exception("Tidak ditemukan checkout component '{$item->nama}' pada hardware asset tujuan.");
                        }

                        $requestedQty = max(1, (int) ($item->jumlah ?? 1));
                        foreach (array_slice($targetCheckoutIds, 0, $requestedQty) as $targetCheckoutId) {
                            $result = $this->snipe->checkinAsset($resource, $targetCheckoutId, $payload);
                            if (in_array(($result['status'] ?? ''), ['error', 'failure'], true)) {
                                $errorMsg = is_array($result['messages'] ?? null)
                                    ? json_encode($result['messages'])
                                    : ($result['messages'] ?? 'Unknown Snipe-IT error');
                                throw new \Exception("Gagal checkin component '{$item->nama}': {$errorMsg}");
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error("Snipe-IT Component Checkin Exception", [
                            'doc_id' => $document->id,
                            'component_id' => $assetId,
                            'message' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                } else {
                    $result = $this->snipe->checkinAsset($resource, $assetId, $payload);
                }

                if (($result['status'] ?? '') === 'error') {
                    $errorMsg = is_array($result['messages'] ?? null)
                        ? json_encode($result['messages'])
                        : ($result['messages'] ?? 'Unknown Snipe-IT error');

                    Log::error('Snipe-IT Checkin Error Response', [
                        'doc_id'   => $document->id,
                        'item_id'  => $item->id,
                        'resource' => $resource,
                        'asset_id' => $assetId,
                        'response' => $result,
                    ]);

                    throw new \Exception("Gagal checkin '{$item->nama}': {$errorMsg}");
                }

                if ($resource !== 'consumables') {
                    $this->recordStbStockHistory(
                        $document,
                        $item,
                        max(1, (int) ($item->jumlah ?? 1)),
                        'STB Pengembalian',
                    );
                }

                Log::info('Snipe-IT Checkin Success', [
                    'doc_id'   => $document->id,
                    'item_id'  => $item->id,
                    'resource' => $resource,
                    'asset_id' => $assetId,
                ]);
            } catch (\Throwable $e) {
                Log::error('Snipe-IT Checkin Exception', [
                    'doc_id'   => $document->id,
                    'item_id'  => $item->id,
                    'resource' => $resource,
                    'asset_id' => $assetId,
                    'message'  => $e->getMessage(),
                ]);
                // Re-throw so the complete() transaction can handle it
                throw $e;
            }
        });
    }

    protected function recordStbStockHistory(mixed $document, mixed $item, int $qty, string $movement): void
    {
        $category = match (strtolower((string) ($item->kategori ?: ''))) {
            'license', 'licenses' => 'license',
            'accessory', 'accessories' => 'accessories',
            'component', 'components' => 'component',
            'consumable', 'consumables' => 'consumable',
            default => null,
        };

        if (!$category || !$item->snipeit_asset_id || $qty === 0) {
            return;
        }

        $documentName = method_exists($this, 'formatStbDocumentName')
            ? $this->formatStbDocumentName($document)
            : "Peminjaman_{$document->id}";
        $documentId = method_exists($this, 'formatDocId')
            ? $this->formatDocId($document, $document->user_company ?? null)
            : (string) $document->id;
        $userName = $document->user_name ?: 'Unknown User';
        $remark = trim((string) ($document->remark ?? ''));
        $serial = trim((string) ($item->serial_no ?? '')) ?: '-';
        $notes = "{$documentName} | Doc ID: {$documentId} | {$movement} | Item: {$item->nama} | Serial: {$serial} | User: {$userName}";
        if ($remark !== '') {
            $notes .= " | Catatan: {$remark}";
        }

        if (\App\Models\AssetStockHistory::query()
            ->where('asset_type', $category)
            ->where('asset_id', (int) $item->snipeit_asset_id)
            ->where('qty', $qty)
            ->where('notes', $notes)
            ->exists()) {
            return;
        }

        \App\Models\AssetStockHistory::create([
            'asset_type' => $category,
            'asset_id' => (int) $item->snipeit_asset_id,
            'qty' => $qty,
            'po_number' => '',
            'purchase_date' => now()->toDateString(),
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Resolve Snipe-IT status ID based on item condition.
     */
    protected function resolveSnipeStatusId(string $condition): int
    {
        $conditionLower = strtolower($condition);
        
        // 1. Define target search terms based on condition
        $searchTerms = match ($conditionLower) {
            'broken', 'rusak' => ['Broken', 'Out for Repair', 'Mati'],
            'missing', 'hilang' => ['Missing', 'Lost', 'Archived'],
            'borrow', 'loan' => ['Borrow', 'Loaner', 'Borrowed', 'Peminjaman', 'Ready to Deploy'],
            default => ['Ready to Deploy', 'Stock', 'Deployable'],
        };

        try {
            $statuses = $this->snipe->fetchRows('statuslabels');
            
            // 2. Try to find an exact name match first
            foreach ($searchTerms as $term) {
                foreach ($statuses as $s) {
                    if (strcasecmp($s['name'], $term) === 0) {
                        return (int) $s['id'];
                    }
                }
            }

            // 3. Fallback: Search by status_type if name search fails
            $targetType = match ($conditionLower) {
                'broken', 'rusak' => 'archived',
                'missing', 'hilang' => 'archived',
                default => 'deployable',
            };

            foreach ($statuses as $s) {
                if (strtolower($s['status_type'] ?? '') === $targetType) {
                    return (int) $s['id'];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to resolve Snipe-IT status ID: ' . $e->getMessage());
        }

        // 4. Ultimate Fallback (using ID 1 which is standard for Ready to Deploy)
        return 1;
    }
}
