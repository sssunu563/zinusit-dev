<?php

namespace App\Services;

use App\Models\UptimeDaily;
use App\Models\UptimeDevice;
use App\Models\UptimeFetchLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UptimePrtgService
{
    // Map site name → PRTG probe/group name
    private const SITE_MAP = [
        'F1 Bogor'      => 'Parung',
        'F2 Karawang'   => 'Karawang',
        'F3 Tangerang'  => 'Tangerang',
    ];

    private string $baseUrl;
    private string $apiToken;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.prtg.url', ''), '/');
        $this->apiToken = (string) config('services.prtg.api_token', '');
        $this->timeout  = (int) config('services.prtg.timeout', 30);
    }

    /**
     * Fetch uptime data for all sites for a given date.
     */
    public function fetchForDate(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $results = [];
        foreach (self::SITE_MAP as $site => $groupName) {
            $results[$site] = $this->fetchSiteForDate($site, $groupName, $date, $triggeredBy, $isManual);
        }
        return $results;
    }

    /**
     * Fetch uptime for a single site.
     */
    public function fetchSiteForDate(string $site, string $groupName, Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $sdate = $date->copy()->startOfDay()->format('Y-m-d-H-i-s');
        $edate = $date->copy()->endOfDay()->format('Y-m-d-H-i-s');
        $reportDate = $date->toDateString();

        $ok   = 0;
        $fail = 0;
        $notes = [];

        try {
            // Step 1: Get devices for this group
            $devicesRes = Http::timeout($this->timeout)->withoutVerifying()
                ->get("{$this->baseUrl}/api/table.json", [
                    'content'      => 'devices',
                    'output'       => 'json',
                    'columns'      => 'objid,device,host,group,probe',
                    'filter_probe' => $groupName,
                    'apitoken'     => $this->apiToken,
                ]);

            $deviceMap = [];
            foreach ($devicesRes->json('devices', []) as $d) {
                $deviceMap[(int) $d['objid']] = [
                    'name'  => $d['device'] ?? '-',
                    'ip'    => $d['host']   ?? '-',
                    'group' => $d['group']  ?? $groupName,
                ];
            }

            // Step 2: Get ping sensors for this group
            $sensorsRes = Http::timeout($this->timeout)->withoutVerifying()
                ->get("{$this->baseUrl}/api/table.json", [
                    'content'       => 'sensors',
                    'output'        => 'json',
                    'columns'       => 'objid,device,group,parentid,sensor,status',
                    'filter_sensor' => 'Ping',
                    'filter_probe'  => $groupName,
                    'apitoken'      => $this->apiToken,
                ]);

            $sensors = $sensorsRes->json('sensors', []);

            foreach ($sensors as $sensor) {
                try {
                    $sensorId  = (int) $sensor['objid'];
                    $parentId  = (int) $sensor['parentid'];
                    $devInfo   = $deviceMap[$parentId] ?? [];
                    $devName   = $devInfo['name'] ?? ($sensor['device'] ?? '-');
                    $ip        = $devInfo['ip']   ?? '-';
                    $hostGroup = $devInfo['group'] ?? $groupName;
                    $lastStatus = strtolower($sensor['status'] ?? '');
                    $status    = str_contains($lastStatus, 'up') ? 'UP' : 'DOWN';

                    // Step 3: Get history for this sensor
                    $histRes = Http::timeout($this->timeout)->withoutVerifying()
                        ->get("{$this->baseUrl}/api/historicdata.json", [
                            'id'       => $sensorId,
                            'avg'      => 300,
                            'sdate'    => $sdate,
                            'edate'    => $edate,
                            'apitoken' => $this->apiToken,
                        ]);

                    $histData = $histRes->json('histdata', []);
                    $upCount  = 0;
                    $downCount = 0;

                    foreach ($histData as $row) {
                        if (($row['coverage'] ?? '') === '0 %') continue;
                        $value = (float) ($row['value_raw'] ?? 0);
                        if ($value == 0) $upCount++;
                        else             $downCount++;
                    }

                    $total   = $upCount + $downCount;
                    $uptime  = $total > 0 ? round($upCount / $total * 100, 3) : 0.0;

                    // Upsert device
                    UptimeDevice::updateOrCreate(
                        ['host_id' => $sensorId],
                        ['device_name' => $devName, 'ip_address' => $ip, 'host_group' => $hostGroup, 'site' => $site]
                    );

                    // Upsert daily record
                    UptimeDaily::updateOrCreate(
                        ['host_id' => $sensorId, 'report_date' => $reportDate],
                        ['uptime_percent' => $uptime, 'status' => $status]
                    );

                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    $notes[] = "Sensor {$sensor['objid']}: " . $e->getMessage();
                    Log::warning("UptimePrtgService sensor error: " . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $fail++;
            $notes[] = "Site {$site}: " . $e->getMessage();
            Log::error("UptimePrtgService site error [{$site}]: " . $e->getMessage());
        }

        $status = match (true) {
            $fail === 0          => 'success',
            $ok > 0 && $fail > 0 => 'partial',
            default              => 'failed',
        };

        UptimeFetchLog::updateOrCreate(
            [
                'fetch_date' => $reportDate,
                'site'       => $site,
            ],
            [
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

    public static function sites(): array
    {
        return array_keys(self::SITE_MAP);
    }
}
