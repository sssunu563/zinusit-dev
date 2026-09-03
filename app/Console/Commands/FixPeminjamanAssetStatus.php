<?php

namespace App\Console\Commands;

use App\Models\Peminjaman;
use App\Services\SnipeItService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixPeminjamanAssetStatus extends Command
{
    protected $signature = 'peminjaman:fix-status {peminjaman_id : ID dokumen peminjaman yang akan diperbaiki}
                            {--dry-run : Tampilkan preview tanpa melakukan perubahan}';
    
    protected $description = 'Perbaiki status asset di Snipe-IT untuk peminjaman yang gagal checkout/checkin';

    public function __construct(
        protected SnipeItService $snipe
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $peminjamanId = (int) $this->argument('peminjaman_id');
        $dryRun = $this->option('dry-run');

        $peminjaman = Peminjaman::with('items')->find($peminjamanId);

        if (!$peminjaman) {
            $this->error("Peminjaman #{$peminjamanId} tidak ditemukan.");
            return 1;
        }

        $this->info("=== Fix Asset Status untuk Peminjaman #{$peminjaman->id} ===");
        $this->info("Movement Type: {$peminjaman->movement_type}");
        $this->info("Status: " . ($peminjaman->is_completed ? 'Completed' : 'Draft'));
        $this->newLine();

        if ($dryRun) {
            $this->warn("⚠️  DRY RUN MODE - Tidak ada perubahan yang akan dilakukan");
            $this->newLine();
        }

        $changes = [];

        foreach ($peminjaman->items as $item) {
            $assetId = $item->snipeit_asset_id ?? $item->computer_id;
            
            if (!$assetId) {
                $this->line("⏭️  Skip: {$item->nama} (tidak ada Snipe-IT ID)");
                continue;
            }

            try {
                $record = $this->snipe->getHardware($assetId);
                $currentStatus = (string) (data_get($record, 'status_label.name') ?? 'Unknown');
                $currentStatusId = (int) (data_get($record, 'status_label.id') ?? 0);
                
                // Tentukan status yang seharusnya
                if ($peminjaman->movement_type === 'out' && $peminjaman->is_completed) {
                    // Completed loan-out → should be Borrow
                    $targetStatus = 'Borrow';
                    $targetStatusId = $this->resolveSnipeStatusId('borrow');
                    
                    if ($currentStatusId !== $targetStatusId) {
                        $changes[] = [
                            'item' => $item->nama,
                            'asset_id' => $assetId,
                            'current' => $currentStatus,
                            'target' => $targetStatus,
                            'target_id' => $targetStatusId,
                            'action' => 'update_status',
                        ];
                    } else {
                        $this->line("✅ {$item->nama} (#{$assetId}): sudah benar '{$currentStatus}'");
                    }
                    
                } elseif ($peminjaman->movement_type === 'return' && $peminjaman->returned_at) {
                    // Completed return → should be Stock/Broken/Missing based on condition
                    $condition = $item->condition ?? 'Good';
                    $targetStatus = match(strtolower($condition)) {
                        'broken', 'rusak' => 'Broken',
                        'missing', 'hilang' => 'Missing',
                        default => 'Ready to Deploy'
                    };
                    $targetStatusId = $this->resolveSnipeStatusId(strtolower($condition));
                    
                    if ($currentStatusId !== $targetStatusId) {
                        $changes[] = [
                            'item' => $item->nama,
                            'asset_id' => $assetId,
                            'current' => $currentStatus,
                            'target' => $targetStatus,
                            'target_id' => $targetStatusId,
                            'action' => 'update_status',
                        ];
                    } else {
                        $this->line("✅ {$item->nama} (#{$assetId}): sudah benar '{$currentStatus}'");
                    }
                }
                
            } catch (\Throwable $e) {
                $this->error("❌ Error pada {$item->nama} (#{$assetId}): {$e->getMessage()}");
            }
        }

        if (empty($changes)) {
            $this->newLine();
            $this->info("✨ Semua asset sudah memiliki status yang benar. Tidak ada yang perlu diperbaiki.");
            return 0;
        }

        $this->newLine();
        $this->warn("🔧 Perubahan yang akan dilakukan:");
        
        $table = [];
        foreach ($changes as $change) {
            $table[] = [
                $change['item'],
                $change['asset_id'],
                $change['current'],
                $change['target'],
            ];
        }
        
        $this->table(['Item', 'Asset ID', 'Status Sekarang', 'Status Target'], $table);

        if ($dryRun) {
            $this->newLine();
            $this->info("Jalankan tanpa --dry-run untuk menerapkan perubahan.");
            return 0;
        }

        $this->newLine();
        if (!$this->confirm('Lanjutkan update status di Snipe-IT?', true)) {
            $this->info('Dibatalkan.');
            return 0;
        }

        // Apply changes
        $success = 0;
        $failed = 0;

        foreach ($changes as $change) {
            try {
                $this->line("⏳ Updating {$change['item']} (#{$change['asset_id']})...");
                
                $result = $this->snipe->updateAsset($change['asset_id'], [
                    'status_id' => $change['target_id'],
                ]);

                if (isset($result['status']) && $result['status'] === 'success') {
                    $this->info("   ✅ Berhasil → '{$change['target']}'");
                    $success++;
                } else {
                    $errorMsg = is_array($result['messages'] ?? null) 
                        ? json_encode($result['messages']) 
                        : ($result['messages'] ?? 'Unknown error');
                    $this->error("   ❌ Gagal: {$errorMsg}");
                    $failed++;
                }
                
            } catch (\Throwable $e) {
                $this->error("   ❌ Exception: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("=== Hasil ===");
        $this->info("✅ Berhasil: {$success}");
        if ($failed > 0) {
            $this->error("❌ Gagal: {$failed}");
        }

        return $failed > 0 ? 1 : 0;
    }

    protected function resolveSnipeStatusId(string $condition): int
    {
        $conditionLower = strtolower($condition);
        
        $searchTerms = match ($conditionLower) {
            'broken', 'rusak' => ['Broken', 'Out for Repair', 'Mati'],
            'missing', 'hilang' => ['Missing', 'Lost', 'Archived'],
            'borrow', 'loan', 'loaner', 'borrowed', 'dipinjam', 'peminjaman' => [
                'Borrow', 'Loaner', 'Borrowed', 'Peminjaman', 'On Loan', 'Dipinjam'
            ],
            default => ['Ready to Deploy', 'Stock', 'Deployable'],
        };

        try {
            $statuses = $this->snipe->fetchRows('statuslabels');
            
            // Try exact name match first
            foreach ($searchTerms as $term) {
                foreach ($statuses as $s) {
                    if (strcasecmp($s['name'], $term) === 0) {
                        return (int) $s['id'];
                    }
                }
            }

            // Fallback: partial match
            foreach ($searchTerms as $term) {
                foreach ($statuses as $s) {
                    if (stripos($s['name'], $term) !== false) {
                        return (int) $s['id'];
                    }
                }
            }

            // Fallback: by status_type
            $targetType = match ($conditionLower) {
                'broken', 'rusak', 'missing', 'hilang' => 'archived',
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

        return 1; // Ultimate fallback
    }
}
