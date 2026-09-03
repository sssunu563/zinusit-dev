<?php

namespace App\Services;

use App\Models\NetworkDevice;
use App\Models\NetworkFetchLog;
use App\Models\NetworkMaintenanceLog;
use App\Models\NetworkUptimeDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NetworkMonitorService
{
    // PRTG: probe → [site, location]
    private const PRTG_PROBE_MAP = [
        'Parung'    => ['site' => 'F1 Bogor',     'location' => 'ZGI BGR F1'],
        'Karawang'  => ['site' => 'F2 Karawang',  'location' => 'ZGI KRW F2'],
        'Tangerang' => ['site' => 'F3 Tangerang', 'location' => 'ZDI TGR F3'],
    ];

    // PRTG: R3 groups override location
    private const PRTG_R3_LOCATION = ['site' => 'F1 Bogor', 'location' => 'ZGI BGR R3'];

    // PRTG: host_groups to include (others excluded)
    private const PRTG_ALLOWED_GROUPS = [
        'Access Point', 'File Server', 'Firewall', 'PBX Server',
        'Payroll Server', 'Router VPN', 'Switch Backbone', 'Switch Core',
        'Switch L2', 'WLC', 'Application Server', 'UPS', 'Server',
    ];

    // Zabbix: instance → [site, location]
    private const ZABBIX_INSTANCE_MAP = [
        'f1' => ['site' => 'F1 Bogor',     'location' => 'ZGI BGR F1'],
        'f2' => ['site' => 'F2 Karawang',  'location' => 'ZGI KRW F2'],
        'f3' => ['site' => 'F3 Tangerang', 'location' => 'ZDI TGR F3'],
    ];

    private int $prtgTimeout;
    private int $zabbixTimeout;

    public function __construct()
    {
        $this->prtgTimeout   = (int) config('services.prtg.timeout', 30);
        $this->zabbixTimeout = (int) config('services.zabbix.timeout', 30);
    }


    /**
     * Fetch from ALL sources (PRTG + all configured Zabbix instances).
     */
    public function fetchAll(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $results = [];

        // PRTG
        $results['prtg'] = $this->fetchPrtg($date, $triggeredBy, $isManual);

        // Zabbix instances
        $instances = config('services.zabbix.instances', []);
        foreach ($instances as $instance => $cfg) {
            if (empty($cfg['url']) || empty($cfg['token'])) continue;
            $results["zabbix_{$instance}"] = $this->fetchZabbixInstance(
                $instance, $cfg['url'], $cfg['token'], $date, $triggeredBy, $isManual
            );
        }

        return $results;
    }

    /**
     * Fetch PRTG only.
     */
    public function fetchPrtg(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $baseUrl  = rtrim((string) config('services.prtg.url', ''), '/');
        $token    = (string) config('services.prtg.api_token', '');
        $avgMin   = (int) config('services.prtg.avg_minutes', 300);

        if (!$baseUrl || !$token) {
            return ['ok' => 0, 'fail' => 0, 'status' => 'failed', 'notes' => ['PRTG not configured']];
        }

        $sdate = $date->copy()->startOfDay()->format('Y-m-d-H-i-s');
        $edate = $date->copy()->endOfDay()->format('Y-m-d-H-i-s');
        $reportDate = $date->toDateString();

        $ok = 0; $fail = 0; $notes = [];

        foreach (self::PRTG_PROBE_MAP as $probe => $siteInfo) {
            try {
                // Get devices
                $devRes = Http::timeout($this->prtgTimeout)->withoutVerifying()
                    ->get("{$baseUrl}/api/table.json", [
                        'content' => 'devices', 'output' => 'json',
                        'columns' => 'objid,device,host,group,probe',
                        'filter_probe' => $probe, 'apitoken' => $token,
                    ]);
                $deviceMap = [];
                foreach ($devRes->json('devices', []) as $d) {
                    $deviceMap[(int) $d['objid']] = [
                        'name'  => $d['device'] ?? '-',
                        'ip'    => $d['host']   ?? '-',
                        'group' => $d['group']  ?? '-',
                    ];
                }

                // Get ping sensors
                $sensorRes = Http::timeout($this->prtgTimeout)->withoutVerifying()
                    ->get("{$baseUrl}/api/table.json", [
                        'content' => 'sensors', 'output' => 'json',
                        'columns' => 'objid,device,group,parentid,sensor,status',
                        'filter_sensor' => 'Ping', 'filter_probe' => $probe,
                        'apitoken' => $token,
                    ]);

                foreach ($sensorRes->json('sensors', []) as $sensor) {
                    try {
                        $sensorId = (int) $sensor['objid'];
                        $parentId = (int) $sensor['parentid'];
                        $dev      = $deviceMap[$parentId] ?? [];
                        $devName  = $dev['name'] ?? ($sensor['device'] ?? '-');
                        $ip       = $dev['ip']   ?? '-';
                        $rawGroup = $dev['group'] ?? ($sensor['group'] ?? '-');

                        // Filter by allowed groups (strip prefix like "F1 ", "F2 ", "R3 ")
                        $cleanGroup = preg_replace('/^(F\d|R\d)\s+/', '', $rawGroup);
                        if (!in_array($cleanGroup, self::PRTG_ALLOWED_GROUPS)) continue;

                        // Resolve location
                        $locInfo = str_starts_with($rawGroup, 'R3 ')
                            ? self::PRTG_R3_LOCATION
                            : $siteInfo;

                        $lastStatus = strtolower($sensor['status'] ?? '');
                        $status     = str_contains($lastStatus, 'up') ? 'UP' : 'DOWN';

                        // Get history
                        $histRes = Http::timeout($this->prtgTimeout)->withoutVerifying()
                            ->get("{$baseUrl}/api/historicdata.json", [
                                'id' => $sensorId, 'avg' => $avgMin,
                                'sdate' => $sdate, 'edate' => $edate,
                                'apitoken' => $token,
                            ]);

                        $upCount = 0; $downCount = 0;
                        foreach ($histRes->json('histdata', []) as $row) {
                            if (($row['coverage'] ?? '') === '0 %') continue;
                            $val = (float) ($row['value_raw'] ?? 0);
                            $val == 0 ? $upCount++ : $downCount++;
                        }
                        $total  = $upCount + $downCount;
                        $uptime = $total > 0 ? round($upCount / $total * 100, 3) : 0.0;

                        // Upsert device
                        $device = NetworkDevice::updateOrCreate(
                            ['source' => 'prtg', 'source_instance' => 'main', 'source_id' => $sensorId],
                            [
                                'device_name' => $devName, 'ip_address' => $ip,
                                'host_group'  => $rawGroup, 'probe' => $probe,
                                'location'    => $locInfo['location'], 'site' => $locInfo['site'],
                                'last_status' => $status, 'last_sync' => now(),
                            ]
                        );

                        // Upsert daily
                        NetworkUptimeDaily::updateOrCreate(
                            ['device_id' => $device->id, 'report_date' => $reportDate],
                            ['uptime_percent' => $uptime, 'status' => $status]
                        );

                        // Auto-create maintenance log if uptime = 0%
                        if ($uptime == 0.0) {
                            $this->autoCreateMaintenanceLog($device->id, $reportDate);
                        }

                        $ok++;
                    } catch (\Throwable $e) {
                        $fail++;
                        $notes[] = "PRTG sensor {$sensor['objid']}: " . $e->getMessage();
                    }
                }
            } catch (\Throwable $e) {
                $fail++;
                $notes[] = "PRTG probe {$probe}: " . $e->getMessage();
                Log::warning("NetworkMonitor PRTG probe {$probe}: " . $e->getMessage());
            }
        }

        $status = $fail === 0 ? 'success' : ($ok > 0 ? 'partial' : 'failed');
        NetworkFetchLog::updateOrCreate(
            [
                'fetch_date'      => $reportDate,
                'source'          => 'prtg',
                'source_instance' => 'main',
            ],
            [
                'group_name'   => 'All Probes',
                'status'       => $status,
                'devices_ok'   => $ok,
                'devices_fail' => $fail,
                'notes'        => $notes ? implode("\n", $notes) : null,
                'triggered_by' => $triggeredBy,
                'is_manual'    => $isManual,
            ]
        );

        return compact('ok', 'fail', 'status', 'notes');
    }

    /**
     * Fetch a single Zabbix instance.
     */
    public function fetchZabbixInstance(
        string $instance, string $url, string $token,
        Carbon $date, ?int $triggeredBy = null, bool $isManual = false
    ): array {
        $timeFrom   = $date->copy()->startOfDay()->timestamp;
        $timeTill   = $date->copy()->endOfDay()->timestamp;
        $reportDate = $date->toDateString();
        $locInfo    = self::ZABBIX_INSTANCE_MAP[$instance] ?? ['site' => $instance, 'location' => $instance];

        $ok = 0; $fail = 0; $notes = []; $processedGroups = [];

        try {
            // Get all host groups
            $groupsRes = $this->zabbixCall($url, $token, 'hostgroup.get', [
                'output' => ['groupid', 'name'],
            ]);

            foreach ($groupsRes as $group) {
                $groupId   = $group['groupid'];
                $groupName = $group['name'];

                // Filter: only groups that match allowed categories
                $cleanGroup = preg_replace('/^(F\d|R\d)\s+(AP|SW|)\s*/i', '', $groupName);
                $matchedCategory = null;
                foreach (self::PRTG_ALLOWED_GROUPS as $allowed) {
                    if (stripos($groupName, $allowed) !== false || stripos($cleanGroup, $allowed) !== false) {
                        $matchedCategory = $allowed;
                        break;
                    }
                }
                // Also allow TP-LINK groups (AP, SW)
                if (!$matchedCategory && !preg_match('/AP|SW|Switch|Access.Point/i', $groupName)) continue;

                try {
                    // Get icmpping items for this group
                    $items = $this->zabbixCall($url, $token, 'item.get', [
                        'groupids'          => $groupId,
                        'filter'            => ['key_' => 'icmpping'],
                        'output'            => ['itemid', 'lastvalue'],
                        'selectHosts'       => ['hostid', 'name'],
                        'selectInterfaces'  => ['ip'],
                    ]);

                    foreach ($items as $item) {
                        try {
                            $host     = $item['hosts'][0] ?? [];
                            $hostId   = (int) ($host['hostid'] ?? 0);
                            $devName  = $host['name'] ?? '-';
                            $ip       = $item['interfaces'][0]['ip'] ?? '-';
                            $status   = ($item['lastvalue'] ?? '0') === '1' ? 'UP' : 'DOWN';

                            // Get history
                            $history = $this->zabbixCall($url, $token, 'history.get', [
                                'output'    => 'extend',
                                'itemids'   => $item['itemid'],
                                'history'   => 3,
                                'time_from' => $timeFrom,
                                'time_till' => $timeTill,
                            ]);

                            $upCount = count(array_filter($history, fn ($h) => ($h['value'] ?? '0') === '1'));
                            $total   = count($history);
                            $uptime  = $total > 0 ? round($upCount / $total * 100, 3) : 0.0;

                            // Upsert device
                            $device = NetworkDevice::updateOrCreate(
                                ['source' => 'zabbix', 'source_instance' => $instance, 'source_id' => $hostId],
                                [
                                    'device_name' => $devName, 'ip_address' => $ip,
                                    'host_group'  => $groupName, 'probe' => "zabbix-{$instance}",
                                    'location'    => $locInfo['location'], 'site' => $locInfo['site'],
                                    'last_status' => $status, 'last_sync' => now(),
                                ]
                            );

                            // Upsert daily
                            NetworkUptimeDaily::updateOrCreate(
                                ['device_id' => $device->id, 'report_date' => $reportDate],
                                ['uptime_percent' => $uptime, 'status' => $status]
                            );

                            // Auto-create maintenance log if uptime = 0%
                            if ($uptime == 0.0) {
                                $this->autoCreateMaintenanceLog($device->id, $reportDate);
                            }

                            $ok++;
                        } catch (\Throwable $e) {
                            $fail++;
                            $hostIdStr = $item['hosts'][0]['hostid'] ?? '?';
                            $notes[] = "Zabbix {$instance} host {$hostIdStr}: " . $e->getMessage();
                        }
                    }

                    $processedGroups[] = $groupName;
                } catch (\Throwable $e) {
                    $fail++;
                    $notes[] = "Zabbix {$instance} group {$groupName}: " . $e->getMessage();
                }
            }
        } catch (\Throwable $e) {
            $fail++;
            $notes[] = "Zabbix {$instance}: " . $e->getMessage();
            Log::error("NetworkMonitor Zabbix {$instance}: " . $e->getMessage());
        }

        $status = $fail === 0 ? 'success' : ($ok > 0 ? 'partial' : 'failed');
        NetworkFetchLog::updateOrCreate(
            [
                'fetch_date'      => $reportDate,
                'source'          => 'zabbix',
                'source_instance' => $instance,
            ],
            [
                'group_name'   => implode(', ', array_slice($processedGroups, 0, 5)) . (count($processedGroups) > 5 ? '...' : ''),
                'status'       => $status,
                'devices_ok'   => $ok,
                'devices_fail' => $fail,
                'notes'        => $notes ? implode("\n", $notes) : null,
                'triggered_by' => $triggeredBy,
                'is_manual'    => $isManual,
            ]
        );

        return compact('ok', 'fail', 'status', 'notes');
    }


    private function zabbixCall(string $url, string $token, string $method, array $params): array
    {
        $res = Http::timeout($this->zabbixTimeout)
            ->withHeaders([
                'Content-Type'  => 'application/json-rpc',
                'Authorization' => 'Bearer ' . $token,
            ])
            ->post($url, [
                'jsonrpc' => '2.0',
                'method'  => $method,
                'params'  => $params,
                'id'      => 1,
            ]);

        return $res->json('result', []);
    }

    public static function sites(): array
    {
        return ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];
    }

    /**
     * Auto-create a maintenance log when uptime = 0%.
     * Only creates if no open log already exists for this device (any date).
     */
    private function autoCreateMaintenanceLog(int $deviceId, string $date): void
    {
        // Skip auto-ticket for excluded devices
        $device = \App\Models\NetworkDevice::find($deviceId);
        if ($device?->is_excluded) return;

        // Check if ANY open log already exists for this device (regardless of date).
        $exists = NetworkMaintenanceLog::where('device_id', $deviceId)
            ->where('status', 'open')
            ->exists();

        if (!$exists) {
            NetworkMaintenanceLog::create([
                'device_id'  => $deviceId,
                'status'     => 'open',
                'started_at' => now(), // High-precision detection time
                'event_type' => 'auto_detected',
                'notes'      => "Device uptime 0% terdeteksi pada " . now()->toDateTimeString() . ". Dibuat otomatis oleh sistem.",
                'is_auto'    => true,
            ]);
        }
    }
}
