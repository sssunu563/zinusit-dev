<?php

namespace App\Console\Commands;

use App\Models\ActionLog;
use App\Models\Inspection;
use Illuminate\Console\Command;

class LinkInspectionActivityLogs extends Command
{
    protected $signature = 'inspection:link-activity-logs';
    protected $description = 'Link existing inspection activity logs to their Snipe-IT assets';

    public function handle(): int
    {
        $linked = 0;

        Inspection::whereNotNull('completed_at')->each(function (Inspection $inspection) use (&$linked): void {
            $snapshot = $inspection->asset_snapshot ? json_decode($inspection->asset_snapshot, true) : [];
            $assetId = (int) ($inspection->snipeit_asset_id ?? ($snapshot['id'] ?? 0));
            if (!$assetId) {
                return;
            }

            $assetType = strtolower((string) ($snapshot['asset_type'] ?? 'assets'));
            $snipeitType = str_contains($assetType, 'accessor')
                ? 'accessories'
                : (str_contains($assetType, 'component') ? 'component' : 'assets');
            $note = "{$inspection->report_id} | Inspection completed ke Snipe-IT"
                . ' | Target: ' . ($inspection->inspection_scope === 'internal_component' ? 'Component internal' : 'Unit');
            if ($inspection->component_name) {
                $note .= " | Component: {$inspection->component_name}";
            }
            $note .= ' | Result: Completed';

            $count = ActionLog::where('item_type', Inspection::class)
                ->where('item_id', $inspection->id)
                ->where('action_type', 'completed')
                ->update([
                    'snipeit_id' => $assetId,
                    'snipeit_type' => $snipeitType,
                    'note' => $note,
                ]);

            $linked += $count;
        });

        $this->info("Linked {$linked} inspection activity log(s).");
        return self::SUCCESS;
    }
}
