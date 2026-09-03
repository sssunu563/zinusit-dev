<?php

namespace App\Http\Controllers;

use App\Models\StbItem;
use App\Models\Inspection;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetTimelineController extends Controller
{
    /**
     * Display the lifecycle/timeline of a specific asset.
     */
    public function show(string $serial)
    {
        // 1. Fetch from STB Items (Handover history)
        $stbItems = StbItem::with('stb')
            ->where('serial_no', $serial)
            ->orWhere('inventory_number', $serial)
            ->get()
            ->map(fn($item) => [
                'id' => 'stb-' . $item->id,
                'date' => $item->stb->created_at->toIso8601String(),
                'timestamp' => $item->stb->created_at->timestamp,
                'type' => 'Handover',
                'title' => strtoupper($item->stb->document_type . ' ' . $item->stb->movement_type),
                'description' => "Dokumen: " . ($item->stb->id_display ?: '#' . $item->stb->id),
                'user' => $item->stb->user_name,
                'location' => $item->stb->location_name,
                'condition' => $item->condition ?: 'Good',
                'link' => route('stb.show', $item->stb_id),
                'tone' => match($item->stb->movement_type) {
                    'out' => 'warning',
                    'return' => 'success',
                    default => 'primary'
                }
            ]);

        // 2. Fetch from Inspections (Maintenance/Check history)
        // We search in device_name and asset_snapshot which might contain the serial
        $inspections = Inspection::where('device_name', 'like', "%$serial%")
            ->orWhere('asset_snapshot', 'like', "%$serial%")
            ->get()
            ->map(fn($insp) => [
                'id' => 'insp-' . $insp->id,
                'date' => $insp->date->toIso8601String(),
                'timestamp' => $insp->date->timestamp,
                'type' => 'Inspection',
                'title' => strtoupper($insp->report_type),
                'description' => $insp->issue_description ?: 'Regular Checkup',
                'user' => $insp->user,
                'location' => $insp->location,
                'condition' => $insp->remarks ?: 'Checked',
                'link' => route('inspection.show', $insp->id),
                'tone' => 'info'
            ]);

        // 3. Merge and Sort
        $timeline = $stbItems->concat($inspections)
            ->sortByDesc('timestamp')
            ->values()
            ->all();

        return Inertia::render('Asset/Timeline', [
            'serial' => $serial,
            'timeline' => $timeline,
        ]);
    }
}
