<?php

namespace App\Console\Commands;

use App\Models\Peminjaman;
use App\Services\SnipeItService;
use Illuminate\Console\Command;

class DiagnosePeminjamanAssets extends Command
{
    protected $signature = 'peminjaman:diagnose {peminjaman_id? : ID dokumen peminjaman}';
    protected $description = 'Diagnose asset status untuk troubleshooting peminjaman/pengembalian';

    public function __construct(
        protected SnipeItService $snipe
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $peminjamanId = $this->argument('peminjaman_id');

        if ($peminjamanId) {
            return $this->diagnoseSingle((int) $peminjamanId);
        }

        return $this->diagnoseRecent();
    }

    protected function diagnoseSingle(int $id): int
    {
        $peminjaman = Peminjaman::with('items')->find($id);

        if (!$peminjaman) {
            $this->error("Peminjaman #{$id} tidak ditemukan.");
            return 1;
        }

        $this->info("=== Diagnosis Peminjaman #{$peminjaman->id} ===");
        $this->info("Movement Type: {$peminjaman->movement_type}");
        $this->info("Status: " . ($peminjaman->is_completed ? 'Completed' : 'Draft'));
        $this->info("Returned: " . ($peminjaman->returned_at ? 'Yes' : 'No'));
        $this->newLine();

        $this->info("Assets:");
        $table = [];

        foreach ($peminjaman->items as $item) {
            $assetId = $item->snipeit_asset_id ?? $item->computer_id;
            
            if (!$assetId) {
                $table[] = [
                    $item->nama,
                    'N/A',
                    'N/A',
                    'N/A',
                    '❌ No Snipe-IT ID'
                ];
                continue;
            }

            try {
                $record = $this->snipe->getHardware($assetId);
                $status = (string) (data_get($record, 'status_label.name')
                    ?? data_get($record, 'status.name')
                    ?? data_get($record, 'status_label')
                    ?? 'Unknown');
                
                $normalizedState = $this->normalizeAssetState($status);
                $assignedTo = data_get($record, 'assigned_to.name', '-');
                
                $icon = match($normalizedState) {
                    'stock' => '📦',
                    'borrow' => '📤',
                    'active' => '✅',
                    default => '⚠️'
                };

                $table[] = [
                    $item->nama,
                    $assetId,
                    $status,
                    $normalizedState,
                    "{$icon} {$assignedTo}"
                ];
            } catch (\Throwable $e) {
                $table[] = [
                    $item->nama,
                    $assetId,
                    'ERROR',
                    'ERROR',
                    '❌ ' . $e->getMessage()
                ];
            }
        }

        $this->table(
            ['Item', 'Asset ID', 'Snipe-IT Status', 'Normalized', 'Assigned To'],
            $table
        );

        // Validation check
        $this->newLine();
        $this->info("=== Validation Check ===");
        
        if ($peminjaman->movement_type === 'out') {
            $allStock = true;
            foreach ($peminjaman->items as $item) {
                $assetId = $item->snipeit_asset_id ?? $item->computer_id;
                if (!$assetId) continue;
                
                try {
                    $record = $this->snipe->getHardware($assetId);
                    $status = (string) (data_get($record, 'status_label.name') ?? '');
                    if ($this->normalizeAssetState($status) !== 'stock') {
                        $allStock = false;
                        break;
                    }
                } catch (\Throwable $e) {
                    $allStock = false;
                    break;
                }
            }
            
            $this->info($allStock 
                ? "✅ Semua asset berstatus Stock - dapat dipinjamkan"
                : "❌ Ada asset yang bukan Stock - tidak dapat dipinjamkan");
        }
        
        if ($peminjaman->movement_type === 'return') {
            $allBorrow = true;
            $problems = [];
            
            foreach ($peminjaman->items as $item) {
                $assetId = $item->snipeit_asset_id ?? $item->computer_id;
                if (!$assetId) continue;
                
                try {
                    $record = $this->snipe->getHardware($assetId);
                    $status = (string) (data_get($record, 'status_label.name') ?? '');
                    $state = $this->normalizeAssetState($status);
                    
                    if ($state !== 'borrow') {
                        $allBorrow = false;
                        $problems[] = "{$item->nama} (#{$assetId}): status '{$status}' → normalized '{$state}'";
                    }
                } catch (\Throwable $e) {
                    $allBorrow = false;
                    $problems[] = "{$item->nama} (#{$assetId}): ERROR - {$e->getMessage()}";
                }
            }
            
            if ($allBorrow) {
                $this->info("✅ Semua asset berstatus Borrow - dapat dikembalikan");
            } else {
                $this->error("❌ Ada asset yang bukan Borrow - tidak dapat dikembalikan");
                $this->newLine();
                $this->warn("Detail masalah:");
                foreach ($problems as $problem) {
                    $this->line("  • {$problem}");
                }
            }
        }

        return 0;
    }

    protected function diagnoseRecent(): int
    {
        $this->info("=== 10 Peminjaman Terakhir ===");
        
        $peminjamans = Peminjaman::with('items')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $table = [];
        foreach ($peminjamans as $p) {
            $table[] = [
                $p->id,
                $p->movement_type,
                $p->items->count(),
                $p->is_completed ? '✅' : '⏳',
                $p->returned_at ? '✅' : '⏳',
                $p->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['ID', 'Type', 'Items', 'Completed', 'Returned', 'Created'],
            $table
        );

        $this->newLine();
        $this->info("Gunakan: php artisan peminjaman:diagnose {id} untuk detail");

        return 0;
    }

    protected function normalizeAssetState(?string $status): ?string
    {
        $normalized = strtolower(trim((string) ($status ?? '')));

        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, 'active')) {
            return 'active';
        }

        if (
            str_contains($normalized, 'stock') ||
            str_contains($normalized, 'ready to deploy') ||
            str_contains($normalized, 'available') ||
            str_contains($normalized, 'deployable')
        ) {
            return 'stock';
        }

        if (
            str_contains($normalized, 'borrow') ||
            str_contains($normalized, 'borrowed') ||
            str_contains($normalized, 'on loan') ||
            str_contains($normalized, 'dipinjam') ||
            str_contains($normalized, 'peminjaman')
        ) {
            return 'borrow';
        }

        return 'unsupported';
    }
}
