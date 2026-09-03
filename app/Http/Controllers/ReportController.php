<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Helpdesk;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/Index');
    }

    public function getStats()
    {
        return response()->json([
            'assetAging' => $this->getAssetAgingData(),
            'slaPerformance' => $this->getSlaData(),
            'consumableRate' => $this->getConsumableData(),
        ]);
    }

    private function getAssetAgingData()
    {
        // Mock data structure for asset aging (e.g. assets by purchase year)
        return [
            ['year' => '2020', 'count' => 12],
            ['year' => '2021', 'count' => 45],
            ['year' => '2022', 'count' => 89],
            ['year' => '2023', 'count' => 64],
            ['year' => '2024', 'count' => 30],
        ];
    }

    private function getSlaData()
    {
        // SLA performance based on Helpdesk tickets
        // Resolved within 24h, 48h, etc.
        try {
            $tickets = Helpdesk::where('status', 'resolved')
                ->whereNotNull('completed_at')
                ->where('created_at', '>=', now()->subMonths(3))
                ->get();

            $total = $tickets->count();
            if ($total === 0) return [
                ['label' => '< 24h', 'count' => 0],
                ['label' => '24-48h', 'count' => 0],
                ['label' => '> 48h', 'count' => 0],
            ];

            $under24 = 0;
            $under48 = 0;
            $over48 = 0;

            foreach ($tickets as $ticket) {
                $created = Carbon::parse($ticket->created_at);
                $completed = Carbon::parse($ticket->completed_at);
                $diff = $created->diffInHours($completed);
                if ($diff < 24) $under24++;
                elseif ($diff < 48) $under48++;
                else $over48++;
            }

            return [
                ['label' => '< 24h', 'count' => $under24],
                ['label' => '24-48h', 'count' => $under48],
                ['label' => '> 48h', 'count' => $over48],
            ];
        } catch (\Exception $e) {
            return [
                ['label' => '< 24h', 'count' => 0],
                ['label' => '24-48h', 'count' => 0],
                ['label' => '> 48h', 'count' => 0],
            ];
        }
    }

    private function getConsumableData()
    {
        // Mock data for consumable burn rate
        return [
            ['month' => 'Jan', 'usage' => 450],
            ['month' => 'Feb', 'usage' => 520],
            ['month' => 'Mar', 'usage' => 380],
            ['month' => 'Apr', 'usage' => 420],
        ];
    }
}
