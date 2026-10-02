<?php

namespace App\Listeners;

use App\Events\SnipeitAssetSynced;
use App\Models\SnipeitAsset;
use App\Services\SnipeItService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncSnipeitAssetToDatabase implements ShouldQueue
{
    public function __construct(
        private readonly SnipeItService $snipe
    ) {
    }

    /**
     * Handle the event - sync single asset to local database
     */
    public function handle(SnipeitAssetSynced $event): void
    {
        try {
            if ($event->action === 'deleted') {
                // Delete from local database
                SnipeitAsset::where('id', $event->assetId)->delete();
                \Log::info("Snipe-IT asset deleted from local DB: #{$event->assetId}");
                return;
            }

            // Fetch from Snipe-IT API
            $asset = $this->snipe->request("hardware/{$event->assetId}");

            if (empty($asset) || !isset($asset['id'])) {
                \Log::warning("Failed to fetch Snipe-IT asset #{$event->assetId}");
                return;
            }

            // Update or create in local database
            SnipeitAsset::updateOrCreate(
                ['id' => $asset['id']],
                [
                    'name' => $asset['name'] ?? $asset['asset_tag'] ?? '-',
                    'asset_tag' => $asset['asset_tag'] ?? null,
                    'serial' => $asset['serial'] ?? null,
                    'model_name' => $asset['model']['name'] ?? null,
                    'status_id' => $asset['status_label']['id'] ?? null,
                    'category_id' => $asset['category']['id'] ?? null,
                    'location_id' => $asset['location']['id'] ?? null,
                    'assigned_to' => $asset['assigned_to']['id'] ?? null,
                    'assigned_type' => $asset['assigned_type'] ?? null,
                    'notes' => $asset['notes'] ?? null,
                    'raw_data' => $asset,
                    'synced_at' => now(),
                ]
            );

            \Log::info("Snipe-IT asset synced to local DB: #{$event->assetId} ({$event->action})");

        } catch (\Exception $e) {
            \Log::error("Failed to sync Snipe-IT asset #{$event->assetId}: {$e->getMessage()}");
        }
    }
}
