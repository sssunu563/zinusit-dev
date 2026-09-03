<?php

namespace App\Console\Commands;

use App\Models\Inspection;
use App\Services\SnipeItService;
use Illuminate\Console\Command;

class UploadInspectionPdf extends Command
{
    protected $signature   = 'inspection:upload-pdf {id : Inspection ID}';
    protected $description = 'Upload the completed PDF of an inspection to its Snipe-IT asset and user';

    public function handle(): int
    {
        $id         = (int) $this->argument('id');
        $inspection = Inspection::find($id);

        if (!$inspection) {
            $this->error("Inspection #{$id} not found.");
            return 1;
        }

        if (!$inspection->completed_pdf_path) {
            $this->error("Inspection #{$id} has no completed PDF.");
            return 1;
        }

        $absPath = storage_path('app/public/' . $inspection->completed_pdf_path);
        if (!file_exists($absPath)) {
            $this->error("PDF file not found: {$absPath}");
            return 1;
        }

        // Resolve asset ID
        $assetId = $inspection->snipeit_asset_id;
        if (!$assetId && $inspection->asset_snapshot) {
            $snap    = json_decode($inspection->asset_snapshot, true);
            $assetId = $snap['id'] ?? null;
        }

        // Determine resource type
        $snapshot     = $inspection->asset_snapshot ? json_decode($inspection->asset_snapshot, true) : [];
        $snapshotType = strtolower($snapshot['asset_type'] ?? '');
        if (str_contains($snapshotType, 'accessor')) {
            $resource = 'accessories';
        } elseif (str_contains($snapshotType, 'component')) {
            $resource = 'components';
        } else {
            $resource = 'hardware';
        }

        $snipe    = app(SnipeItService::class);
        $content  = file_get_contents($absPath);
        $filename = basename($absPath);
        $notes    = "Inspection Report: {$inspection->report_id}";
        $success  = true;

        // 1. Upload to asset
        if ($assetId) {
            $this->info("Uploading PDF to Snipe-IT {$resource} #{$assetId}...");
            try {
                $result = $snipe->uploadFile($resource, $assetId, $content, $filename, $notes);
                if (isset($result['status']) && $result['status'] === 'error') {
                    $this->error('Asset upload error: ' . json_encode($result['messages'] ?? $result));
                    $success = false;
                } else {
                    $this->info("✓ PDF uploaded to {$resource} #{$assetId}.");
                }
            } catch (\Exception $e) {
                $this->error('Asset upload failed: ' . $e->getMessage());
                $success = false;
            }
        } else {
            $this->warn('No asset ID found — skipping asset upload.');
        }

        // 2. Upload to user
        $userId = $inspection->user_snipeit_id;
        if ($userId) {
            $this->info("Uploading PDF to Snipe-IT user #{$userId}...");
            try {
                $result = $snipe->uploadFile('users', (int) $userId, $content, $filename, $notes);
                if (isset($result['status']) && $result['status'] === 'error') {
                    $this->error('User upload error: ' . json_encode($result['messages'] ?? $result));
                    $success = false;
                } else {
                    $this->info("✓ PDF uploaded to user #{$userId}.");
                }
            } catch (\Exception $e) {
                $this->error('User upload failed: ' . $e->getMessage());
                $success = false;
            }
        } else {
            $this->warn('No user_snipeit_id found — skipping user upload.');
        }

        return $success ? 0 : 1;
    }
}
