<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

use App\Models\NetworkDevice;
use App\Models\CctvDevice;
use App\Models\ServerDevice;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InfraReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/InfraReport/Index');
    }

    public function data(Request $request)
    {
        try {
            $from = $request->input('from') ?: now()->subDays(6)->toDateString();
            $to   = $request->input('to')   ?: now()->toDateString();

            Log::debug('InfraReport Data Request', ['from' => $from, 'to' => $to]);

            // Sites stored as "F1 Bogor", "F2 Karawang", "F3 Tangerang"
            $sites = ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];

            $data = [
                'network'   => $this->getUptimeReport($sites, 'network', $from, $to),
                'nvr'       => $this->getUptimeReport($sites, 'nvr',     $from, $to),
                'cctv'      => $this->getUptimeReport($sites, 'cctv',    $from, $to),
                'bandwidth' => $this->getBandwidthReport($sites, $from, $to),
                'server'    => $this->getUptimeReport($sites, 'server',  $from, $to),
                'helpdesk'  => $this->getHelpdeskReport($sites, $from, $to),
            ];
 
            Log::debug('InfraReport Compiled Data', [
                'network_count'   => count($data['network']),
                'bandwidth_count' => count($data['bandwidth']),
                'helpdesk_count'  => count($data['helpdesk']),
            ]);

            return response()->json($data);

        } catch (\Throwable $e) {
            Log::error('InfraReport data error: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error'     => $e->getMessage(),
                'file'      => basename($e->getFile()),
                'line'      => $e->getLine(),
                'network'   => [],
                'nvr'       => [],
                'cctv'      => [],
                'bandwidth' => [],
                'server'    => [],
                'helpdesk'  => [],
            ], 200);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = $request->from ?? now()->subDays(6)->toDateString();
        $to   = $request->to   ?? now()->toDateString();

        $fileName = 'Weekly_Infra_Report_' . $from . '_to_' . $to . '.xlsx';

        return (new \App\Exports\InfraReportExport($from, $to))->download($fileName);
    }

    private function getUptimeReport(array $sites, string $type, string $from, string $to): array
    {
        $results = [];

        foreach ($sites as $site) {
            // ── 1. Get devices for this site ─────────────────────────────
            if ($type === 'network') {
                $devices = NetworkDevice::where('site', $site)->where('is_active', true)->get();
            } elseif ($type === 'server') {
                $devices = ServerDevice::where('site', $site)->where('is_active', true)->get();
            } else {
                // nvr / cctv
                $devices = CctvDevice::where('site', $site)
                    ->where('device_type', strtoupper($type))
                    ->where('is_active', true)
                    ->get();
            }

            $deviceIds = $devices->pluck('id');
            $qty       = $deviceIds->count();

            if ($qty === 0) {
                $results[] = [
                    'location'    => $site,
                    'qty'         => 0,
                    'uptime'      => 100.0,
                    'failed_list' => []
                ];
                continue;
            }

            // ── 2. Compute average uptime & failed list ───────────────────
            if ($type === 'server') {
                $sourceIds = $devices->pluck('source_id');
                $rows = DB::table('server_resource_daily')
                    ->whereIn('host_id', $sourceIds)
                    ->whereBetween('report_date', [$from, $to])
                    ->get();

                $daysCount    = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
                $totalSlots   = $qty * $daysCount;
                $presentSlots = $rows->count();
                $avgUptime    = $totalSlots > 0 ? round(($presentSlots / $totalSlots) * 100, 2) : 100.0;

                $presentHostIds = $rows->pluck('host_id')->unique();
                $downDevices    = $devices->filter(fn ($d) => !$presentHostIds->contains($d->source_id));
                $failedList     = $downDevices->map(function ($d) use ($from) {
                    $log = DB::table('server_maintenance_logs')
                        ->where('device_id', $d->id)
                        ->where('started_at', '<=', $from . ' 23:59:59')
                        ->where(fn($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from . ' 00:00:00'))
                        ->first();

                        $duration = '-';
                        if ($log) {
                            $start = Carbon::parse($log->started_at);
                            $end   = $log->resolved_at ? Carbon::parse($log->resolved_at) : now();
                            $diff  = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) $parts[] = "{$diff->d}d";
                            if ($diff->h > 0) $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT) . "h";
                            if ($diff->i > 0) $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT) . "m";
                            $duration = empty($parts) ? '0s' : implode(' ', $parts);
                        }

                    return [
                        'id'             => $log?->id,
                        'device_id'      => $d->id,
                        'device_name'    => $d->device_name,
                        'ip_address'     => $d->ip_address,
                        'report_date'    => $from,
                        'uptime_percent' => 0,
                        'duration'       => $duration,
                        'started_at'     => $log?->started_at,
                        'resolved_at'    => $log?->resolved_at,
                        'remark'         => $log?->notes ?? '-',
                    ];
                })->values()->toArray();

            } elseif ($type === 'network') {
                $avgUptime = DB::table('network_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$from, $to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('network_uptime_daily')
                    ->join('network_devices', 'network_uptime_daily.device_id', '=', 'network_devices.id')
                    ->leftJoin('network_maintenance_logs', function($join) {
                        $join->on('network_uptime_daily.device_id', '=', 'network_maintenance_logs.device_id')
                             ->on('network_uptime_daily.report_date', '=', DB::raw('DATE(network_maintenance_logs.started_at)'));
                    })
                    ->whereIn('network_uptime_daily.device_id', $deviceIds)
                    ->whereBetween('network_uptime_daily.report_date', [$from, $to])
                    ->where('network_uptime_daily.uptime_percent', '<', 100)
                    ->select(
                        'network_maintenance_logs.id',
                        'network_devices.id as device_id',
                        'network_devices.device_name',
                        'network_devices.ip_address',
                        'network_uptime_daily.report_date',
                        'network_uptime_daily.uptime_percent',
                        'network_maintenance_logs.started_at',
                        'network_maintenance_logs.resolved_at',
                        'network_maintenance_logs.notes as remark'
                    )
                    ->orderBy('network_uptime_daily.report_date', 'desc')
                    ->get()
                    ->map(function ($r) {
                        $arr = (array) $r;
                        $duration = '-';
                        if (!empty($r->started_at)) {
                            $start = Carbon::parse($r->started_at);
                            $end   = $r->resolved_at ? Carbon::parse($r->resolved_at) : now();
                            $diff  = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) $parts[] = "{$diff->d}d";
                            if ($diff->h > 0) $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT) . "h";
                            if ($diff->i > 0) $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT) . "m";
                            $duration = empty($parts) ? '0s' : implode(' ', $parts);
                        } else {
                            $uptime = (float)($r->uptime_percent ?? 100);
                            if ($uptime < 100) {
                                $downSeconds = round((1 - $uptime / 100) * 86400);
                                $h = floor($downSeconds / 3600);
                                $m = floor(($downSeconds % 3600) / 60);
                                $duration = ($h > 0 ? "{$h}h " : "") . ($m > 0 ? "{$m}m" : "");
                                if (empty($duration)) $duration = "0s";
                            }
                        }
                        $arr['duration'] = trim($duration);
                        return $arr;
                    })
                    ->toArray();

            } else {
                // cctv / nvr
                $avgUptime = DB::table('cctv_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$from, $to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('cctv_uptime_daily')
                    ->join('cctv_devices', 'cctv_uptime_daily.device_id', '=', 'cctv_devices.id')
                    ->leftJoin('cctv_maintenance_logs', function($join) {
                        $join->on('cctv_uptime_daily.device_id', '=', 'cctv_maintenance_logs.device_id')
                             ->on('cctv_uptime_daily.report_date', '=', DB::raw('DATE(cctv_maintenance_logs.started_at)'));
                    })
                    ->whereIn('cctv_uptime_daily.device_id', $deviceIds)
                    ->whereBetween('cctv_uptime_daily.report_date', [$from, $to])
                    ->where('cctv_uptime_daily.uptime_percent', '<', 100)
                    ->select(
                        'cctv_maintenance_logs.id',
                        'cctv_devices.id as device_id',
                        'cctv_devices.device_name',
                        'cctv_devices.ip_address',
                        'cctv_uptime_daily.report_date',
                        'cctv_uptime_daily.uptime_percent',
                        'cctv_maintenance_logs.started_at',
                        'cctv_maintenance_logs.resolved_at',
                        'cctv_maintenance_logs.notes as remark'
                    )
                    ->orderBy('cctv_uptime_daily.report_date', 'desc')
                    ->get()
                    ->map(function ($r) {
                        $arr = (array) $r;
                        $duration = '-';
                        if (!empty($r->started_at)) {
                            $start = Carbon::parse($r->started_at);
                            $end   = $r->resolved_at ? Carbon::parse($r->resolved_at) : now();
                            $diff  = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) $parts[] = "{$diff->d}d";
                            if ($diff->h > 0) $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT) . "h";
                            if ($diff->i > 0) $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT) . "m";
                            $duration = empty($parts) ? '0s' : implode(' ', $parts);
                        } else {
                            $uptime = (float)($r->uptime_percent ?? 100);
                            if ($uptime < 100) {
                                $downSeconds = round((1 - $uptime / 100) * 86400);
                                $h = floor($downSeconds / 3600);
                                $m = floor(($downSeconds % 3600) / 60);
                                $duration = ($h > 0 ? "{$h}h " : "") . ($m > 0 ? "{$m}m" : "");
                                if (empty($duration)) $duration = "0s";
                            }
                        }
                        $arr['duration'] = trim($duration);
                        return $arr;
                    })
                    ->toArray();
            }

            $results[] = [
                'location'    => $site,
                'qty'         => $qty,
                'uptime'      => round((float) $avgUptime, 2),
                'failed_list' => $failedList,
            ];
        }

        return $results;
    }

    private function getBandwidthReport(array $sites, string $from, string $to): array
    {
        $results = [];
        foreach ($sites as $site) {
            $cleanSite = str_ireplace(['F1 ', 'F2 ', 'F3 '], '', $site);
            $fct = '';
            if (str_starts_with($site, 'F1')) $fct = 'F1';
            elseif (str_starts_with($site, 'F2')) $fct = 'F2';
            elseif (str_starts_with($site, 'F3')) $fct = 'F3';

            $rows = DB::table('bandwidth_daily')
                ->where('location', 'like', "%$cleanSite%")
                ->whereBetween('report_date', [$from, $to])
                ->select(
                    'provider',
                    'description',
                    'remark',
                    DB::raw('AVG(value_mbps) as avg_mbps')
                )
                ->groupBy('provider', 'description', 'remark')
                ->orderBy('provider')
                ->get();

            $contractsQuery = DB::table('isp_sla_contracts')
                ->where('location', 'like', "%$cleanSite%");
            
            if ($fct) {
                $contractsQuery->where('fct', $fct);
            }

            $contracts = $contractsQuery->get()->keyBy(fn($c) => strtoupper($c->provider));

            $providers = [];
            foreach ($rows as $row) {
                $p = $row->provider;
                $pKey = strtoupper($p);
                if (!isset($providers[$p])) {
                    $limit = isset($contracts[$pKey]) ? (float)$contracts[$pKey]->bandwidth : 0;
                    $providers[$p] = [
                        'provider'        => $p,
                        'device_name'     => $p,
                        'ip_address'      => '-',
                        'remark'          => $row->remark ?? '-',
                        'avg_download'    => null,
                        'avg_upload'      => null,
                        'bandwidth_limit' => $limit
                    ];
                }
                if (str_contains(strtolower($row->description ?? ''), 'download')) {
                    $providers[$p]['avg_download'] = round($row->avg_mbps, 2);
                } elseif (str_contains(strtolower($row->description ?? ''), 'upload')) {
                    $providers[$p]['avg_upload'] = round($row->avg_mbps, 2);
                }
            }

            $results[] = [
                'location'  => $site,
                'providers' => array_values($providers),
            ];
        }
        return $results;
    }

    private function getHelpdeskReport(array $sites, string $from, string $to): array
    {
        $results = [];
        foreach ($sites as $site) {
            $siteKey = str_replace(' Bogor', '', $site);
            $siteKey = str_replace(' Karawang', '', $siteKey);
            $siteKey = str_replace(' Tangerang', '', $siteKey);
            
            $query = Ticket::where(function($q) use ($site, $siteKey) {
                $q->where('location', 'like', "%$site%")
                  ->orWhere('location', 'like', "%$siteKey%");
            })->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

            $total = (clone $query)->count();
            $closed = (clone $query)->whereIn('status', ['closed', 'resolved'])->count();

            $performance = $total > 0 ? round(($closed / $total) * 100, 2) : 100.0;

            $pending = (clone $query)
                ->select('id', 'location', 'issue_description', 'action_taken', 'created_at', 'status')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($t) {
                    $duration = '-';
                    if ($t->created_at) {
                        $start = $t->created_at;
                        $end   = $t->date_closed ? Carbon::parse($t->date_closed) : now();
                        $diff  = $start->diff($end);
                        $parts = [];
                        if ($diff->d > 0) $parts[] = "{$diff->d}d";
                        if ($diff->h > 0) $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT) . "h";
                        if ($diff->i > 0) $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT) . "m";
                        $duration = empty($parts) ? '0s' : implode(' ', $parts);
                    }

                    return [
                        'id'         => $t->id,
                        'location'   => $t->location,
                        'date'       => $t->created_at?->toDateString(),
                        'duration'   => $duration,
                        'case'       => $t->issue_description,
                        'remark'     => $t->action_taken ?? '-',
                        'status'     => $t->status,
                    ];
                });

            $results[] = [
                'location'     => $site,
                'case'         => $total,
                'closed'       => $closed,
                'performance'  => $performance,
                'pending_list' => $pending,
            ];
        }
        return $results;
    }

    public function updateBandwidthRemark(Request $request)
    {
        $request->validate([
            'id'     => 'required',
            'remark' => 'nullable|string'
        ]);

        DB::table('bandwidth_daily')
            ->where('id', $request->id)
            ->update(['remark' => $request->remark]);

        return response()->json(['message' => 'Remark updated']);
    }

    public function updateHelpdeskRemark(Request $request)
    {
        $request->validate([
            'id'     => 'required',
            'case'   => 'nullable|string',
            'remark' => 'nullable|string'
        ]);

        $ticket = Ticket::findOrFail($request->id);
        $ticket->issue_description = $request->case;
        $ticket->action_taken = $request->remark;
        $ticket->save();

        return response()->json(['message' => 'Helpdesk info updated']);
    }
}
