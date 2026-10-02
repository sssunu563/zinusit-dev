<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\CctvDevice;
use App\Models\CctvMaintenanceLog;
use App\Models\IspSlaContract;
use App\Models\NetworkDevice;
use App\Models\NetworkMaintenanceLog;
use App\Models\ServerDevice;
use App\Models\ServerMaintenanceLog;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InfraReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Report/InfraReport/Index');
    }

    public static function ensureDefaultBandwidthContracts(): void
    {
        $defaults = [
            ['location' => 'Bogor', 'fct' => 'F1', 'provider' => 'ISAT', 'bandwidth' => 180.0, 'target_pct' => 99.5, 'is_active' => true, 'sort_order' => 1],
            ['location' => 'Bogor', 'fct' => 'F1', 'provider' => 'TGG', 'bandwidth' => 100.0, 'target_pct' => 99.5, 'is_active' => true, 'sort_order' => 2],
            ['location' => 'Karawang', 'fct' => 'F2', 'provider' => 'ISAT', 'bandwidth' => 180.0, 'target_pct' => 99.5, 'is_active' => true, 'sort_order' => 3],
            ['location' => 'Karawang', 'fct' => 'F2', 'provider' => 'TGG', 'bandwidth' => 80.0, 'target_pct' => 99.5, 'is_active' => true, 'sort_order' => 4],
            ['location' => 'Tangerang', 'fct' => 'F3', 'provider' => 'BIZNET', 'bandwidth' => 240.0, 'target_pct' => 99.5, 'is_active' => true, 'sort_order' => 5],
            ['location' => 'Tangerang', 'fct' => 'F3', 'provider' => 'TGG', 'bandwidth' => 100.0, 'target_pct' => 99.5, 'is_active' => true, 'sort_order' => 6],
        ];

        foreach ($defaults as $d) {
            IspSlaContract::firstOrCreate(
                ['fct' => $d['fct'], 'provider' => $d['provider']],
                $d
            );
        }
    }

    public function deviceSettings(Request $request)
    {
        self::ensureDefaultBandwidthContracts();

        return Inertia::render('Report/InfraReport/DeviceSettings', [
            'devices' => $this->deviceSettingsData(),
            'bandwidthContracts' => IspSlaContract::orderBy('sort_order')->get(),
            'failedDevices' => $this->getRecentFailedDevices(),
        ]);
    }

    public function updateDeviceSetting(Request $request, string $type, int $id)
    {
        $validated = $request->validate(['included' => 'required|boolean']);
        $model = $this->deviceModel($type);
        $device = $model::findOrFail($id);
        $device->update(['is_excluded' => ! $validated['included']]);

        return response()->json(['success' => true, 'included' => ! $device->is_excluded]);
    }

    public function batchUpdateDeviceSettings(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:network,nvr,cctv,server',
            'included_ids' => 'present|array',
            'included_ids.*' => 'integer',
            'site' => 'nullable|string',
        ]);

        $model = $this->deviceModel($request->type);
        $query = $model::where('is_active', true);
        if ($request->type === 'nvr') {
            $query->where('device_type', 'NVR');
        } elseif ($request->type === 'cctv') {
            $query->where('device_type', 'CCTV');
        }
        if ($request->site && $request->site !== 'all') {
            $query->where('site', $request->site);
        }

        $allIds = $query->pluck('id')->toArray();
        $includedIds = array_map('intval', $request->included_ids);

        $toInclude = array_intersect($allIds, $includedIds);
        $toExclude = array_diff($allIds, $includedIds);

        if (! empty($toInclude)) {
            $model::whereIn('id', $toInclude)->update(['is_excluded' => false]);
        }
        if (! empty($toExclude)) {
            $model::whereIn('id', $toExclude)->update(['is_excluded' => true]);
        }

        return response()->json([
            'success' => true,
            'included_count' => count($toInclude),
            'excluded_count' => count($toExclude),
        ]);
    }

    public function updateBandwidthCapacity(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:isp_sla_contracts,id',
            'bandwidth' => 'required|numeric|min:0',
            'target_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $contract = IspSlaContract::findOrFail($validated['id']);
        $contract->update([
            'bandwidth' => $validated['bandwidth'],
            'target_pct' => $validated['target_pct'] ?? $contract->target_pct,
        ]);

        return response()->json(['success' => true, 'contract' => $contract]);
    }

    public function saveMaintenanceLog(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'device_type' => 'required|string|in:network,server,cctv,nvr',
            'device_id' => 'required|integer',
            'started_at' => 'required|string',
            'resolved_at' => 'nullable|string',
            'event_type' => 'nullable|string',
            'status' => 'nullable|string|in:open,closed',
            'notes' => 'nullable|string',
        ]);

        $status = $validated['status'] ?? (! empty($validated['resolved_at']) ? 'closed' : 'open');
        $started = Carbon::parse($validated['started_at'])->toDateTimeString();
        $resolved = ! empty($validated['resolved_at']) ? Carbon::parse($validated['resolved_at'])->toDateTimeString() : null;

        $payload = [
            'device_id' => $validated['device_id'],
            'status' => $status,
            'started_at' => $started,
            'resolved_at' => $resolved,
            'event_type' => $validated['event_type'] ?? 'maintenance',
            'notes' => $validated['notes'] ?? '',
            'created_by' => auth()->id(),
        ];

        $logClass = match ($validated['device_type']) {
            'network' => NetworkMaintenanceLog::class,
            'server' => ServerMaintenanceLog::class,
            'cctv', 'nvr' => CctvMaintenanceLog::class,
        };

        if (! empty($validated['id'])) {
            $log = $logClass::findOrFail($validated['id']);
            $log->update($payload);
        } else {
            $log = $logClass::create($payload);
        }

        return response()->json(['success' => true, 'log' => $log]);
    }

    public function deleteMaintenanceLog(string $type, int $id)
    {
        $logClass = match ($type) {
            'network' => NetworkMaintenanceLog::class,
            'server' => ServerMaintenanceLog::class,
            'cctv', 'nvr' => CctvMaintenanceLog::class,
            default => abort(404),
        };

        $logClass::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function data(Request $request)
    {
        try {
            $from = $request->input('from') ?: now()->subDays(6)->toDateString();
            $to = $request->input('to') ?: now()->toDateString();

            Log::debug('InfraReport Data Request', ['from' => $from, 'to' => $to]);

            // Sites stored as "F1 Bogor", "F2 Karawang", "F3 Tangerang"
            $sites = ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];

            $data = [
                'network' => $this->getUptimeReport($sites, 'network', $from, $to),
                'nvr' => $this->getUptimeReport($sites, 'nvr', $from, $to),
                'cctv' => $this->getUptimeReport($sites, 'cctv', $from, $to),
                'bandwidth' => $this->getBandwidthReport($sites, $from, $to),
                'server' => $this->getUptimeReport($sites, 'server', $from, $to),
                'helpdesk' => $this->getHelpdeskReport($sites, $from, $to),
            ];

            Log::debug('InfraReport Compiled Data', [
                'network_count' => count($data['network']),
                'bandwidth_count' => count($data['bandwidth']),
                'helpdesk_count' => count($data['helpdesk']),
            ]);

            return response()->json($data);

        } catch (\Throwable $e) {
            Log::error('InfraReport data error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'network' => [],
                'nvr' => [],
                'cctv' => [],
                'bandwidth' => [],
                'server' => [],
                'helpdesk' => [],
            ], 200);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $from = $request->from ?? now()->subDays(7)->toDateString();
        $to = $request->to ?? now()->subDays(1)->toDateString();

        $fileName = 'Weekly_Infra_Report_'.$from.'_to_'.$to.'.xlsx';

        return (new \App\Exports\InfraReportExport($from, $to))->download($fileName);
    }

    private function getUptimeReport(array $sites, string $type, string $from, string $to): array
    {
        $results = [];

        foreach ($sites as $site) {
            // ── 1. Get devices for this site ─────────────────────────────
            if ($type === 'network') {
                $devices = NetworkDevice::where('site', $site)->where('is_active', true)->where('is_excluded', false)->get();
            } elseif ($type === 'server') {
                $devices = ServerDevice::where('site', $site)->where('is_active', true)->where('is_excluded', false)->get();
            } else {
                // nvr / cctv
                $devices = CctvDevice::where('site', $site)
                    ->where('device_type', strtoupper($type))
                    ->where('is_active', true)
                    ->where('is_excluded', false)
                    ->get();
            }

            $deviceIds = $devices->pluck('id');
            $qty = $deviceIds->count();

            if ($qty === 0) {
                $results[] = [
                    'location' => $site,
                    'qty' => 0,
                    'uptime' => 100.0,
                    'failed_list' => [],
                ];

                continue;
            }

            // ── 2. Compute average uptime & failed list ───────────────────
            if ($type === 'server') {
                // Server Operation doesn't track uptime - use maintenance logs as downtime indicator
                // Calculate uptime based on days without maintenance logs
                $daysCount = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;

                // Get maintenance logs in period
                $maintenanceLogs = DB::table('server_maintenance_logs')
                    ->whereIn('device_id', $deviceIds)
                    ->where(function ($q) use ($from, $to) {
                        $q->where('started_at', '<=', $to.' 23:59:59')
                            ->where(fn ($sq) => $sq->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from.' 00:00:00'));
                    })
                    ->get();

                // Calculate total downtime days across all devices
                $totalDowntimeDays = 0;
                foreach ($maintenanceLogs as $log) {
                    $start = Carbon::parse($log->started_at);
                    $end = $log->resolved_at ? Carbon::parse($log->resolved_at) : Carbon::parse($to);
                    $start = $start->max(Carbon::parse($from));
                    $end = $end->min(Carbon::parse($to));
                    if ($start <= $end) {
                        $totalDowntimeDays += $start->diffInDays($end) + 1;
                    }
                }

                $totalSlots = $qty * $daysCount;
                $avgUptime = $totalSlots > 0 ? round((($totalSlots - $totalDowntimeDays) / $totalSlots) * 100, 2) : 100.0;

                // Failed devices = those with maintenance logs in period
                $downDevices = $devices->filter(function ($d) use ($maintenanceLogs) {
                    return $maintenanceLogs->contains('device_id', $d->id);
                });
                $failedList = $downDevices->map(function ($d) use ($from, $maintenanceLogs) {
                    $log = $maintenanceLogs->where('device_id', $d->id)
                        ->sortByDesc('started_at')
                        ->first();

                    $duration = '-';
                    if ($log) {
                        $start = Carbon::parse($log->started_at);
                        $end = $log->resolved_at ? Carbon::parse($log->resolved_at) : now();
                        $diff = $start->diff($end);
                        $parts = [];
                        if ($diff->d > 0) {
                            $parts[] = "{$diff->d}d";
                        }
                        if ($diff->h > 0) {
                            $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT).'h';
                        }
                        if ($diff->i > 0) {
                            $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT).'m';
                        }
                        $duration = empty($parts) ? '0s' : implode(' ', $parts);
                    }

                    return [
                        'id' => $log?->id,
                        'device_id' => $d->id,
                        'device_name' => $d->device_name,
                        'ip_address' => $d->ip_address,
                        'report_date' => $from,
                        'uptime_percent' => 0,
                        'duration' => $duration,
                        'started_at' => $log?->started_at,
                        'resolved_at' => $log?->resolved_at,
                        'remark' => $log?->notes ?? '-',
                    ];
                })->values()->toArray();

            } elseif ($type === 'network') {
                $avgUptime = DB::table('network_uptime_daily')
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('report_date', [$from, $to])
                    ->avg('uptime_percent') ?? 100.0;

                $failedList = DB::table('network_uptime_daily')
                    ->join('network_devices', 'network_uptime_daily.device_id', '=', 'network_devices.id')
                    ->leftJoin('network_maintenance_logs', function ($join) {
                        $join->on('network_uptime_daily.device_id', '=', 'network_maintenance_logs.device_id')
                            ->where(function ($q) {
                                $q->whereRaw('network_uptime_daily.report_date >= DATE(network_maintenance_logs.started_at)')
                                    ->whereRaw('(network_maintenance_logs.resolved_at IS NULL OR network_uptime_daily.report_date <= DATE(network_maintenance_logs.resolved_at))');
                            });
                    })
                    ->whereIn('network_uptime_daily.device_id', $deviceIds)
                    ->whereBetween('network_uptime_daily.report_date', [$from, $to])
                    ->where('network_uptime_daily.uptime_percent', '<', 90) // Match Network Operation target 90%
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
                        if (! empty($r->started_at)) {
                            $start = Carbon::parse($r->started_at);
                            $end = $r->resolved_at ? Carbon::parse($r->resolved_at) : now();
                            $diff = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) {
                                $parts[] = "{$diff->d}d";
                            }
                            if ($diff->h > 0) {
                                $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT).'h';
                            }
                            if ($diff->i > 0) {
                                $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT).'m';
                            }
                            $duration = empty($parts) ? '0s' : implode(' ', $parts);
                        } else {
                            $uptime = (float) ($r->uptime_percent ?? 100);
                            if ($uptime < 100) {
                                $downSeconds = round((1 - $uptime / 100) * 86400);
                                $h = floor($downSeconds / 3600);
                                $m = floor(($downSeconds % 3600) / 60);
                                $duration = ($h > 0 ? "{$h}h " : '').($m > 0 ? "{$m}m" : '');
                                if (empty($duration)) {
                                    $duration = '0s';
                                }
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
                    ->leftJoin('cctv_maintenance_logs', function ($join) {
                        $join->on('cctv_uptime_daily.device_id', '=', 'cctv_maintenance_logs.device_id')
                            ->where(function ($q) {
                                $q->whereRaw('cctv_uptime_daily.report_date >= DATE(cctv_maintenance_logs.started_at)')
                                    ->whereRaw('(cctv_maintenance_logs.resolved_at IS NULL OR cctv_uptime_daily.report_date <= DATE(cctv_maintenance_logs.resolved_at))');
                            });
                    })
                    ->whereIn('cctv_uptime_daily.device_id', $deviceIds)
                    ->whereBetween('cctv_uptime_daily.report_date', [$from, $to])
                    ->where('cctv_uptime_daily.uptime_percent', '<', 95) // Match CCTV Operation warning threshold 95%
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
                        if (! empty($r->started_at)) {
                            $start = Carbon::parse($r->started_at);
                            $end = $r->resolved_at ? Carbon::parse($r->resolved_at) : now();
                            $diff = $start->diff($end);
                            $parts = [];
                            if ($diff->d > 0) {
                                $parts[] = "{$diff->d}d";
                            }
                            if ($diff->h > 0) {
                                $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT).'h';
                            }
                            if ($diff->i > 0) {
                                $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT).'m';
                            }
                            $duration = empty($parts) ? '0s' : implode(' ', $parts);
                        } else {
                            $uptime = (float) ($r->uptime_percent ?? 100);
                            if ($uptime < 100) {
                                $downSeconds = round((1 - $uptime / 100) * 86400);
                                $h = floor($downSeconds / 3600);
                                $m = floor(($downSeconds % 3600) / 60);
                                $duration = ($h > 0 ? "{$h}h " : '').($m > 0 ? "{$m}m" : '');
                                if (empty($duration)) {
                                    $duration = '0s';
                                }
                            }
                        }
                        $arr['duration'] = trim($duration);

                        return $arr;
                    })
                    ->toArray();
            }

            $results[] = [
                'location' => $site,
                'qty' => $qty,
                'uptime' => round((float) $avgUptime, 2),
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
            if (str_starts_with($site, 'F1')) {
                $fct = 'F1';
            } elseif (str_starts_with($site, 'F2')) {
                $fct = 'F2';
            } elseif (str_starts_with($site, 'F3')) {
                $fct = 'F3';
            }

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

            $contracts = $contractsQuery->get()->keyBy(fn ($c) => strtoupper($c->provider));

            $providers = [];
            foreach ($rows as $row) {
                $p = $row->provider;
                $pKey = strtoupper($p);
                if (! isset($providers[$p])) {
                    $limit = isset($contracts[$pKey]) ? (float) $contracts[$pKey]->bandwidth : 0;
                    $providers[$p] = [
                        'provider' => $p,
                        'device_name' => $p,
                        'ip_address' => '-',
                        'remark' => $row->remark ?? '-',
                        'avg_download' => null,
                        'avg_upload' => null,
                        'bandwidth_limit' => $limit,
                    ];
                }
                if (str_contains(strtolower($row->description ?? ''), 'download')) {
                    $providers[$p]['avg_download'] = round($row->avg_mbps, 2);
                } elseif (str_contains(strtolower($row->description ?? ''), 'upload')) {
                    $providers[$p]['avg_upload'] = round($row->avg_mbps, 2);
                }
            }

            $results[] = [
                'location' => $site,
                'providers' => array_values($providers),
            ];
        }

        return $results;
    }

    private function deviceModel(string $type): string
    {
        return match ($type) {
            'network' => NetworkDevice::class,
            'nvr', 'cctv' => CctvDevice::class,
            'server' => ServerDevice::class,
            default => abort(404),
        };
    }

    private function deviceSettingsData(): array
    {
        return [
            'network' => NetworkDevice::where('is_active', true)->orderBy('site')->orderBy('device_name')->get()->map(fn ($device) => $this->mapDeviceSetting($device, 'network'))->values(),
            'nvr' => CctvDevice::where('device_type', 'NVR')->where('is_active', true)->orderBy('site')->orderBy('device_name')->get()->map(fn ($device) => $this->mapDeviceSetting($device, 'nvr'))->values(),
            'cctv' => CctvDevice::where('device_type', 'CCTV')->where('is_active', true)->orderBy('site')->orderBy('device_name')->get()->map(fn ($device) => $this->mapDeviceSetting($device, 'cctv'))->values(),
            'server' => ServerDevice::where('is_active', true)->orderBy('site')->orderBy('device_name')->get()->map(fn ($device) => $this->mapDeviceSetting($device, 'server'))->values(),
        ];
    }

    private function mapDeviceSetting($device, string $type): array
    {
        return [
            'id' => $device->id,
            'type' => $type,
            'device_name' => $device->device_name,
            'ip_address' => $device->ip_address,
            'site' => $device->site,
            'location' => $device->location,
            'host_group' => $device->host_group,
            'included' => ! $device->is_excluded,
        ];
    }

    private function getRecentFailedDevices(): array
    {
        $from = now()->subDays(13)->toDateString();
        $to = now()->toDateString();
        $list = [];

        // 1. Network devices
        $downNetDevices = NetworkDevice::where('is_active', true)
            ->where('is_excluded', false)
            ->where(function ($q) use ($from, $to) {
                $q->whereExists(function ($sub) use ($from, $to) {
                    $sub->select(DB::raw(1))
                        ->from('network_uptime_daily')
                        ->whereColumn('network_uptime_daily.device_id', 'network_devices.id')
                        ->whereBetween('report_date', [$from, $to])
                        ->where('uptime_percent', '<', 95); // Changed from 100 to 95
                })
                    ->orWhereExists(function ($sub) use ($from, $to) {
                        $sub->select(DB::raw(1))
                            ->from('network_maintenance_logs')
                            ->whereColumn('network_maintenance_logs.device_id', 'network_devices.id')
                            ->where('started_at', '<=', $to.' 23:59:59')
                            ->where(fn ($sq) => $sq->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from.' 00:00:00'));
                    });
            })
            ->orderBy('site')
            ->orderBy('device_name')
            ->get();

        foreach ($downNetDevices as $d) {
            $log = NetworkMaintenanceLog::where('device_id', $d->id)
                ->orderByDesc('started_at')
                ->first();

            $worstUptime = DB::table('network_uptime_daily')
                ->where('device_id', $d->id)
                ->whereBetween('report_date', [$from, $to])
                ->min('uptime_percent');

            $list[] = [
                'id' => $log?->id,
                'device_id' => $d->id,
                'device_name' => $d->device_name,
                'ip_address' => $d->ip_address,
                'site' => $d->site,
                'category' => 'Network',
                'type' => 'network',
                'is_excluded' => (bool) $d->is_excluded,
                'uptime_percent' => $worstUptime !== null ? (float) $worstUptime : 0,
                'started_at' => $log?->started_at ? Carbon::parse($log->started_at)->format('Y-m-d\TH:i') : null,
                'resolved_at' => $log?->resolved_at ? Carbon::parse($log->resolved_at)->format('Y-m-d\TH:i') : null,
                'event_type' => $log?->event_type ?? 'maintenance',
                'status' => $log?->status ?? ($log?->resolved_at ? 'closed' : 'open'),
                'remark' => $log?->notes ?? '',
            ];
        }

        // 2. CCTV & NVR devices
        $downCctvDevices = CctvDevice::where('is_active', true)
            ->where('is_excluded', false)
            ->where(function ($q) use ($from, $to) {
                $q->whereExists(function ($sub) use ($from, $to) {
                    $sub->select(DB::raw(1))
                        ->from('cctv_uptime_daily')
                        ->whereColumn('cctv_uptime_daily.device_id', 'cctv_devices.id')
                        ->whereBetween('report_date', [$from, $to])
                        ->where('uptime_percent', '<', 95); // Changed from 100 to 95
                })
                    ->orWhereExists(function ($sub) use ($from, $to) {
                        $sub->select(DB::raw(1))
                            ->from('cctv_maintenance_logs')
                            ->whereColumn('cctv_maintenance_logs.device_id', 'cctv_devices.id')
                            ->where('started_at', '<=', $to.' 23:59:59')
                            ->where(fn ($sq) => $sq->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from.' 00:00:00'));
                    });
            })
            ->orderBy('site')
            ->orderBy('device_name')
            ->get();

        foreach ($downCctvDevices as $d) {
            $log = CctvMaintenanceLog::where('device_id', $d->id)
                ->orderByDesc('started_at')
                ->first();

            $worstUptime = DB::table('cctv_uptime_daily')
                ->where('device_id', $d->id)
                ->whereBetween('report_date', [$from, $to])
                ->min('uptime_percent');

            $cat = strtoupper($d->device_type ?? 'CCTV');
            $list[] = [
                'id' => $log?->id,
                'device_id' => $d->id,
                'device_name' => $d->device_name,
                'ip_address' => $d->ip_address,
                'site' => $d->site,
                'category' => $cat,
                'type' => strtolower($cat),
                'is_excluded' => (bool) $d->is_excluded,
                'uptime_percent' => $worstUptime !== null ? (float) $worstUptime : 0,
                'started_at' => $log?->started_at ? Carbon::parse($log->started_at)->format('Y-m-d\TH:i') : null,
                'resolved_at' => $log?->resolved_at ? Carbon::parse($log->resolved_at)->format('Y-m-d\TH:i') : null,
                'event_type' => $log?->event_type ?? 'maintenance',
                'status' => $log?->status ?? ($log?->resolved_at ? 'closed' : 'open'),
                'remark' => $log?->notes ?? '',
            ];
        }

        // 3. Server devices
        $downServerDevices = ServerDevice::where('is_active', true)
            ->where('is_excluded', false)
            ->whereExists(function ($sub) use ($from, $to) {
                $sub->select(DB::raw(1))
                    ->from('server_maintenance_logs')
                    ->whereColumn('server_maintenance_logs.device_id', 'server_devices.id')
                    ->where('started_at', '<=', $to.' 23:59:59')
                    ->where(fn ($sq) => $sq->whereNull('resolved_at')->orWhere('resolved_at', '>=', $from.' 00:00:00'));
            })
            ->orderBy('site')
            ->orderBy('device_name')
            ->get();

        foreach ($downServerDevices as $d) {
            $log = ServerMaintenanceLog::where('device_id', $d->id)
                ->orderByDesc('started_at')
                ->first();

            $list[] = [
                'id' => $log?->id,
                'device_id' => $d->id,
                'device_name' => $d->device_name,
                'ip_address' => $d->ip_address,
                'site' => $d->site,
                'category' => 'Server',
                'type' => 'server',
                'is_excluded' => (bool) $d->is_excluded,
                'uptime_percent' => 0,
                'started_at' => $log?->started_at ? Carbon::parse($log->started_at)->format('Y-m-d\TH:i') : null,
                'resolved_at' => $log?->resolved_at ? Carbon::parse($log->resolved_at)->format('Y-m-d\TH:i') : null,
                'event_type' => $log?->event_type ?? 'maintenance',
                'status' => $log?->status ?? ($log?->resolved_at ? 'closed' : 'open'),
                'remark' => $log?->notes ?? '',
            ];
        }

        return $list;
    }

    private function getHelpdeskReport(array $sites, string $from, string $to): array
    {
        // Use raw locations from tickets like SupportOperation does
        $locations = Ticket::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->distinct()
            ->orderBy('location')
            ->pluck('location')
            ->filter()
            ->values();

        $results = [];
        foreach ($locations as $location) {
            $query = Ticket::where('location', $location)
                ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);

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
                        $end = $t->date_closed ? Carbon::parse($t->date_closed) : now();
                        $diff = $start->diff($end);
                        $parts = [];
                        if ($diff->d > 0) {
                            $parts[] = "{$diff->d}d";
                        }
                        if ($diff->h > 0) {
                            $parts[] = str_pad($diff->h, 2, '0', STR_PAD_LEFT).'h';
                        }
                        if ($diff->i > 0) {
                            $parts[] = str_pad($diff->i, 2, '0', STR_PAD_LEFT).'m';
                        }
                        $duration = empty($parts) ? '0s' : implode(' ', $parts);
                    }

                    return [
                        'id' => $t->id,
                        'location' => $t->location,
                        'date' => $t->created_at?->toDateString(),
                        'duration' => $duration,
                        'case' => $t->issue_description,
                        'remark' => $t->action_taken ?? '-',
                        'status' => $t->status,
                    ];
                });

            $results[] = [
                'location' => $location,
                'case' => $total,
                'closed' => $closed,
                'performance' => $performance,
                'pending_list' => $pending,
            ];
        }

        return $results;
    }

    public function updateBandwidthRemark(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'remark' => 'nullable|string',
        ]);

        DB::table('bandwidth_daily')
            ->where('id', $request->id)
            ->update(['remark' => $request->remark]);

        return response()->json(['message' => 'Remark updated']);
    }

    public function updateHelpdeskRemark(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'case' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        $ticket = Ticket::findOrFail($request->id);
        $ticket->issue_description = $request->case;
        $ticket->action_taken = $request->remark;
        $ticket->save();

        return response()->json(['message' => 'Helpdesk info updated']);
    }
}
