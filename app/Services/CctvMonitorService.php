<?php

namespace App\Services;

use App\Models\CctvDevice;
use App\Models\CctvFetchLog;
use App\Models\CctvMaintenanceLog;
use App\Models\CctvUptimeDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CctvMonitorService
{
    // Zabbix instance → [site, location]
    private const ZABBIX_INSTANCE_MAP = [
        'f1' => ['site' => 'F1 Bogor',     'location' => 'ZGI BGR F1'],
        'f2' => ['site' => 'F2 Karawang',  'location' => 'ZGI KRW F2'],
        'f3' => ['site' => 'F3 Tangerang', 'location' => 'ZDI TGR F3'],
    ];

    // PRTG: probe → [site, location]
    private const PRTG_PROBE_MAP = [
        'Parung'    => ['site' => 'F1 Bogor',     'location' => 'ZGI BGR F1'],
        'Karawang'  => ['site' => 'F2 Karawang',  'location' => 'ZGI KRW F2'],
        'Tangerang' => ['site' => 'F3 Tangerang', 'location' => 'ZDI TGR F3'],
    ];

    private int $prtgTimeout;
    private int $prtgConnectTimeout;
    private int $zabbixTimeout;
    private array $zabbixCctvGroups;
    private array $zabbixFingerGroups;
    private array $prtgNvrGroups;

    public function __construct()
    {
        $this->prtgTimeout        = (int) config('services.prtg.timeout', 30);
        $this->prtgConnectTimeout  = (int) config('services.prtg.connect_timeout', 5);
        $this->zabbixTimeout      = (int) config('services.zabbix.timeout', 30);
        $this->zabbixCctvGroups   = config('services.cctv_monitor.zabbix_cctv_groups',   ['CCTV']);
        $this->zabbixFingerGroups = config('services.cctv_monitor.zabbix_finger_groups', ['FINGERPRINT']);
        $this->prtgNvrGroups      = config('services.cctv_monitor.prtg_nvr_groups',      ['NVR']);
    }

    /** Resolve device_type from Zabbix group name using configurable keywords */
    private function resolveZabbixDeviceType(string $groupName): ?string
    {
        foreach ($this->zabbixCctvGroups as $kw) {
            if (stripos($groupName, $kw) !== false) return 'cctv';
        }
        foreach ($this->zabbixFingerGroups as $kw) {
            if (stripos($groupName, $kw) !== false) return 'finger';
        }
        return null;
    }

    /** Check if a PRTG group name matches NVR keywords */
    private function isPrtgNvrGroup(string $groupName): bool
    {
        foreach ($this->prtgNvrGroups as $kw) {
            if (stripos($groupName, $kw) !== false) return true;
        }
        return false;
    }

    /**
     * Fetch all sources: Zabbix (CCTV + Finger) + PRTG (NVR).
     */
    public function fetchAll(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $results = [];

        // PRTG NVR
        $results['prtg_nvr'] = $this->fetchPrtgNvr($date, $triggeredBy, $isManual);

        // Zabbix instances (CCTV + Finger)
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
     * Fetch PRTG NVR devices only.
     */
    public function fetchPrtgNvr(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $baseUrl    = rtrim((string) config('services.prtg.url', ''), '/');
        $token      = (string) config('services.prtg.api_token', '');
        $avgMin     = (int) config('services.prtg.avg_minutes', 300);
        $reportDate = $date->toDateString();
        $sdate      = $date->copy()->startOfDay()->format('Y-m-d-H-i-s');
        $edate      = $date->copy()->endOfDay()->format('Y-m-d-H-i-s');

        if (!$baseUrl || !$token) {
            return ['ok' => 0, 'fail' => 0, 'status' => 'failed', 'notes' => ['PRTG not configured']];
        }

        $ok = 0; $fail = 0; $notes = [];

        foreach (self::PRTG_PROBE_MAP as $probe => $siteInfo) {
            try {
                // Get devices
                $devRes = Http::timeout($this->prtgTimeout)->connectTimeout($this->prtgConnectTimeout)->withoutVerifying()
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

                // Get ping sensors — filter by NVR group
                $sensorRes = Http::timeout($this->prtgTimeout)->connectTimeout($this->prtgConnectTimeout)->withoutVerifying()
                    ->get("{$baseUrl}/api/table.json", [
                        'content' => 'sensors', 'output' => 'json',
                        'columns' => 'objid,device,group,parentid,sensor,status',
                        'filter_sensor' => 'Ping', 'filter_probe' => $probe,
                        'apitoken' => $token,
                    ]);

                foreach ($sensorRes->json('sensors', []) as $sensor) {
                    try {
                        $parentId = (int) $sensor['parentid'];
                        $dev      = $deviceMap[$parentId] ?? [];
                        $rawGroup = $dev['group'] ?? ($sensor['group'] ?? '-');

                        // Only NVR groups
                        if (!$this->isPrtgNvrGroup($rawGroup)) continue;

                        $sensorId = (int) $sensor['objid'];
                        $devName  = $dev['name'] ?? ($sensor['device'] ?? '-');
                        $ip       = $dev['ip']   ?? '-';
                        $status   = str_contains(strtolower($sensor['status'] ?? ''), 'up') ? 'UP' : 'DOWN';

                        // Extract location from device name (e.g., "NVR F1.1" → "F1", "NVR R3.1" → "R3")
                        $location = $siteInfo['location'];
                        if (preg_match('/\b(F\d|R\d)\b/', $devName, $matches)) {
                            $locPrefix = $matches[1]; // F1, F2, F3, R3, etc.
                            // Map location prefix to full location string
                            $locationMap = [
                                'F1' => 'ZGI BGR F1',
                                'F2' => 'ZGI KRW F2',
                                'F3' => 'ZDI TGR F3',
                                'R3' => 'ZGI BGR R3',
                            ];
                            $location = $locationMap[$locPrefix] ?? $siteInfo['location'];
                        }

                        // Get history
                        $histRes = Http::timeout($this->prtgTimeout)->connectTimeout($this->prtgConnectTimeout)->withoutVerifying()
                            ->get("{$baseUrl}/api/historicdata.json", [
                                'id' => $sensorId, 'avg' => $avgMin,
                                'sdate' => $sdate, 'edate' => $edate,
                                'apitoken' => $token,
                            ]);

                        $upCount = 0; $downCount = 0;
                        foreach ($histRes->json('histdata', []) as $row) {
                            if (($row['coverage'] ?? '') === '0 %') continue;
                            (float)($row['value_raw'] ?? 0) == 0 ? $upCount++ : $downCount++;
                        }
                        $total  = $upCount + $downCount;
                        $uptime = $total > 0 ? round($upCount / $total * 100, 3) : 0.0;

                        $device = CctvDevice::updateOrCreate(
                            ['source' => 'prtg', 'source_instance' => 'main', 'source_id' => $sensorId],
                            [
                                'device_name' => $devName, 'ip_address' => $ip,
                                'host_group'  => $rawGroup, 'device_type' => 'nvr',
                                'location'    => $location, 'site' => $siteInfo['site'],
                                'last_status' => $status, 'last_sync' => now(),
                            ]
                        );

                        CctvUptimeDaily::updateOrCreate(
                            ['device_id' => $device->id, 'report_date' => $reportDate],
                            ['uptime_percent' => $uptime, 'status' => $status]
                        );

                        if ($uptime == 0.0) $this->autoCreateMaintenanceLog($device->id, $reportDate);

                        $ok++;
                    } catch (\Throwable $e) {
                        $fail++;
                        $notes[] = "PRTG NVR sensor {$sensor['objid']}: " . $e->getMessage();
                    }
                }
            } catch (\Throwable $e) {
                $fail++;
                $notes[] = "PRTG probe {$probe}: " . $e->getMessage();
                Log::warning("CctvMonitor PRTG {$probe}: " . $e->getMessage());
            }
        }

        $status = $fail === 0 ? 'success' : ($ok > 0 ? 'partial' : 'failed');
        CctvFetchLog::updateOrCreate(
            [
                'fetch_date'      => $reportDate,
                'source'          => 'prtg',
                'source_instance' => 'main',
                'device_type'     => 'nvr',
            ],
            [
                'group_name'   => 'NVR',
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
     * Fetch a single Zabbix instance for CCTV and Fingerprint groups.
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
            $groupsRes = $this->zabbixCall($url, $token, 'hostgroup.get', [
                'output' => ['groupid', 'name'],
            ]);

            foreach ($groupsRes as $group) {
                $groupName = $group['name'];

                // Determine device_type from group name using configurable keywords
                $deviceType = $this->resolveZabbixDeviceType($groupName);
                if (!$deviceType) continue;

                try {
                    $items = $this->zabbixCall($url, $token, 'item.get', [
                        'groupids'         => $group['groupid'],
                        'filter'           => ['key_' => 'icmpping'],
                        'output'           => ['itemid', 'lastvalue'],
                        'selectHosts'      => ['hostid', 'name'],
                        'selectInterfaces' => ['ip'],
                    ]);

                    foreach ($items as $item) {
                        try {
                            $host    = $item['hosts'][0] ?? [];
                            $hostId  = (int) ($host['hostid'] ?? 0);
                            $devName = trim($host['name'] ?? '');
                            $ip      = $item['interfaces'][0]['ip'] ?? '-';

                            // Use IP as name if name is empty
                            if ($devName === '') $devName = $ip !== '-' ? $ip : "device-{$hostId}";

                            $status = ($item['lastvalue'] ?? '0') === '1' ? 'UP' : 'DOWN';

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

                            $device = CctvDevice::updateOrCreate(
                                ['source' => 'zabbix', 'source_instance' => $instance, 'source_id' => $hostId],
                                [
                                    'device_name' => $devName, 'ip_address' => $ip,
                                    'host_group'  => $groupName, 'device_type' => $deviceType,
                                    'location'    => $locInfo['location'], 'site' => $locInfo['site'],
                                    'last_status' => $status, 'last_sync' => now(),
                                ]
                            );

                            CctvUptimeDaily::updateOrCreate(
                                ['device_id' => $device->id, 'report_date' => $reportDate],
                                ['uptime_percent' => $uptime, 'status' => $status]
                            );

                            if ($uptime == 0.0) $this->autoCreateMaintenanceLog($device->id, $reportDate);

                            $ok++;
                        } catch (\Throwable $e) {
                            $fail++;
                            $notes[] = "Zabbix {$instance} host " . ($item['hosts'][0]['hostid'] ?? '?') . ': ' . $e->getMessage();
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
            Log::error("CctvMonitor Zabbix {$instance}: " . $e->getMessage());
        }

        $status = $fail === 0 ? 'success' : ($ok > 0 ? 'partial' : 'failed');
        CctvFetchLog::updateOrCreate(
            [
                'fetch_date'      => $reportDate,
                'source'          => 'zabbix',
                'source_instance' => $instance,
                'device_type'     => 'cctv_finger',
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
        $connectTimeout = (int) config('services.zabbix.connect_timeout', 5);

        $res = Http::timeout($this->zabbixTimeout)
            ->connectTimeout($connectTimeout)
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

        if (!$res->successful()) {
            throw new \RuntimeException("Zabbix HTTP {$res->status()} for {$method}");
        }

        return $res->json('result', []);
    }

    private function autoCreateMaintenanceLog(int $deviceId, string $date): void
    {
        $device = CctvDevice::find($deviceId);
        if ($device?->is_excluded) return;

        // Block creation if ANY open ticket already exists for this device (any date).
        $exists = CctvMaintenanceLog::where('device_id', $deviceId)
            ->where('status', 'open')
            ->exists();

        if (!$exists) {
            CctvMaintenanceLog::create([
                'device_id'  => $deviceId,
                'status'     => 'open',
                'started_at' => now(),
                'event_type' => 'auto_detected',
                'notes'      => "Device uptime 0% terdeteksi pada " . now()->toDateTimeString() . ". Dibuat otomatis oleh sistem.",
                'is_auto'    => true,
            ]);
        }
    }

    public static function sites(): array
    {
        return ['F1 Bogor', 'F2 Karawang', 'F3 Tangerang'];
    }
}
