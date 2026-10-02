<?php

namespace App\Http\Controllers;

use App\Models\CctvFetchLog;
use App\Models\ServerFetchLog;
use App\Models\NetworkFetchLog;
use App\Models\BandwidthFetchLog;
use App\Models\UptimeFetchLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FetchLogsController extends Controller
{
    public function index(Request $request): Response
    {
        // Collect all fetch logs from different sources
        $logs = collect();

        // CCTV Logs
        $cctvLogs = CctvFetchLog::latest('created_at')
            ->limit(500)
            ->get()
            ->map(fn($log) => [
                'id' => 'cctv_' . $log->id,
                'fetch_date' => $log->fetch_date,
                'source' => $log->source,
                'source_instance' => $log->source_instance,
                'device_type' => $log->device_type, // nvr, cctv, finger
                'group_name' => $log->group_name,
                'devices_ok' => $log->devices_ok,
                'devices_fail' => $log->devices_fail,
                'status' => $log->status,
                'is_manual' => $log->is_manual,
                'triggered_by' => $log->triggered_by,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ]);

        $logs = $logs->merge($cctvLogs);

        // Server Logs
        if (class_exists(ServerFetchLog::class)) {
            $serverLogs = ServerFetchLog::latest('created_at')
                ->limit(500)
                ->get()
                ->map(fn($log) => [
                    'id' => 'server_' . $log->id,
                    'fetch_date' => $log->fetch_date,
                    'source' => $log->source ?? 'prtg',
                    'source_instance' => $log->source_instance ?? null,
                    'device_type' => 'server',
                    'group_name' => $log->group_name ?? null,
                    'devices_ok' => $log->devices_ok ?? 0,
                    'devices_fail' => $log->devices_fail ?? 0,
                    'status' => $log->status ?? 'success',
                    'is_manual' => $log->is_manual ?? false,
                    'triggered_by' => $log->triggered_by ?? 'system',
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ]);
            
            $logs = $logs->merge($serverLogs);
        }

        // Network Logs
        if (class_exists(NetworkFetchLog::class)) {
            $networkLogs = NetworkFetchLog::latest('created_at')
                ->limit(500)
                ->get()
                ->map(fn($log) => [
                    'id' => 'network_' . $log->id,
                    'fetch_date' => $log->fetch_date,
                    'source' => $log->source ?? 'prtg',
                    'source_instance' => $log->source_instance ?? null,
                    'device_type' => 'network',
                    'group_name' => $log->group_name ?? null,
                    'devices_ok' => $log->devices_ok ?? 0,
                    'devices_fail' => $log->devices_fail ?? 0,
                    'status' => $log->status ?? 'success',
                    'is_manual' => $log->is_manual ?? false,
                    'triggered_by' => $log->triggered_by ?? 'system',
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ]);
            
            $logs = $logs->merge($networkLogs);
        }

        // Bandwidth Logs
        if (class_exists(BandwidthFetchLog::class)) {
            $bandwidthLogs = BandwidthFetchLog::latest('created_at')
                ->limit(500)
                ->get()
                ->map(fn($log) => [
                    'id' => 'bandwidth_' . $log->id,
                    'fetch_date' => $log->fetch_date,
                    'source' => $log->source ?? 'prtg',
                    'source_instance' => $log->source_instance ?? null,
                    'device_type' => 'bandwidth',
                    'group_name' => $log->group_name ?? null,
                    'devices_ok' => $log->devices_ok ?? 0,
                    'devices_fail' => $log->devices_fail ?? 0,
                    'status' => $log->status ?? 'success',
                    'is_manual' => $log->is_manual ?? false,
                    'triggered_by' => $log->triggered_by ?? 'system',
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ]);
            
            $logs = $logs->merge($bandwidthLogs);
        }

        // Sort by created_at desc
        $logs = $logs->sortByDesc('created_at')->values();

        return Inertia::render('FetchLogs/Index', [
            'logs' => $logs->toArray(),
        ]);
    }
}
