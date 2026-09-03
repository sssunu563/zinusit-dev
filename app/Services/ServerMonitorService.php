<?php

namespace App\Services;

use App\Models\ServerDevice;
use App\Models\ServerFetchLog;
use App\Models\ServerResourceDaily;
use App\Models\ServerTemperatureDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServerMonitorService
{
    private const CPU_SENSORS = [
        ['id' => 4064, 'site' => 'F1 Bogor'],
        ['id' => 4797, 'site' => 'F1 Bogor'],
        ['id' => 5274, 'site' => 'F1 Bogor'],
        ['id' => 5331, 'site' => 'F1 Bogor'],
        ['id' => 5348, 'site' => 'F1 Bogor'],
        ['id' => 3760, 'site' => 'F2 Karawang'],
        ['id' => 4621, 'site' => 'F2 Karawang'],
        ['id' => 3647, 'site' => 'F2 Karawang'],
        ['id' => 3913, 'site' => 'F3 Tangerang'],
        ['id' => 4575, 'site' => 'F3 Tangerang'],
    ];

    private const MEMORY_SENSORS = [
        ['id' => 4068, 'site' => 'F1 Bogor'],
        ['id' => 4798, 'site' => 'F1 Bogor'],
        ['id' => 5279, 'site' => 'F1 Bogor'],
        ['id' => 5333, 'site' => 'F1 Bogor'],
        ['id' => 5349, 'site' => 'F1 Bogor'],
        ['id' => 3649, 'site' => 'F2 Karawang'],
        ['id' => 4623, 'site' => 'F2 Karawang'],
        ['id' => 3762, 'site' => 'F2 Karawang'],
        ['id' => 4688, 'site' => 'F3 Tangerang'],
        ['id' => 3916, 'site' => 'F3 Tangerang'],
    ];

    private const DISK_SENSORS = [
        ['id' => 4119, 'site' => 'F1 Bogor',    'keyword' => 'Free Space sda3'],
        ['id' => 4799, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 4800, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 5275, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 5276, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 5329, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 5330, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 5342, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 3645, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 3646, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 4619, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 4620, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 3805, 'site' => 'F2 Karawang', 'keyword' => 'Free Space md126p4'],
        ['id' => 4689, 'site' => 'F3 Tangerang','keyword' => 'Free Space'],
        ['id' => 4690, 'site' => 'F3 Tangerang','keyword' => 'Free Space'],
        ['id' => 3908, 'site' => 'F3 Tangerang', 'keyword' => 'Free Space'],
        ['id' => 5661, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 5543, 'site' => 'F1 Bogor',    'keyword' => 'Free Space'],
        ['id' => 4719, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 3889, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 5555, 'site' => 'F2 Karawang', 'keyword' => 'Free Space'],
        ['id' => 5619, 'site' => 'F3 Tangerang', 'keyword' => 'Free Space'],
        ['id' => 5569, 'site' => 'F3 Tangerang', 'keyword' => 'Free Space'],
    ];

    private const QNAP_SENSORS = [
        ['id' => 5522, 'site' => 'F1 Bogor'],
        ['id' => 5656, 'site' => 'F1 Bogor'],
        ['id' => 5437, 'site' => 'F1 Bogor'],
        ['id' => 5565, 'site' => 'F2 Karawang'],
        ['id' => 5568, 'site' => 'F2 Karawang'],
        ['id' => 5556, 'site' => 'F2 Karawang'],
        ['id' => 4653, 'site' => 'F3 Tangerang'],
        ['id' => 5551, 'site' => 'F3 Tangerang']
    ];

    private const QNAP_DISK_SENSORS = [
        ['id' => 5661, 'site' => 'F1 Bogor'],
        ['id' => 5543, 'site' => 'F1 Bogor'],
        ['id' => 4719, 'site' => 'F2 Karawang'],
        ['id' => 3889, 'site' => 'F2 Karawang'],
        ['id' => 5555, 'site' => 'F2 Karawang'],
        ['id' => 5619, 'site' => 'F3 Tangerang'],
        ['id' => 5569, 'site' => 'F3 Tangerang']
    ];

    private const TEMPERATURE_SENSORS = [
        '2340' => ['location' => 'F1 Bogor',     'description' => 'F1 Server Room Temp', 'keyword' => 'Inlet Temp Sensor'],
        '5799' => ['location' => 'F2 Karawang',  'description' => 'F2 Server Room Temp', 'keyword' => 'Inlet Temp Sensor'],
        '5727' => ['location' => 'F3 Tangerang', 'description' => 'F3 Server Room Temp', 'keyword' => 'Air inlet'],
    ];

    private string $baseUrl;
    private string $apiToken;
    private int $timeout;
    private int $avgMinutes;

    public function __construct()
    {
        $this->baseUrl    = rtrim(config('services.prtg.url'), '/');
        $this->apiToken   = config('services.prtg.api_token');
        $this->timeout    = (int) config('services.prtg.timeout', 30);
        $this->avgMinutes = (int) config('services.prtg.avg_minutes', 300);
    }

    public function fetchAll(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $results = [];
        $results['resources']   = $this->fetchResources($date, $triggeredBy, $isManual);
        $results['temperature'] = $this->fetchTemperature($date, $triggeredBy, $isManual);
        return $results;
    }

    public function fetchResources(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $reportDate = $date->toDateString();
        $sdate = $date->copy()->startOfDay()->format('Y-m-d-H-i-s');
        $edate = $date->copy()->endOfDay()->format('Y-m-d-H-i-s');

        $ok = 0; $fail = 0; $notes = [];
        
        // Group metrics by parent_id (PRTG Device ID)
        $deviceMetrics = [];

        // 1. Process CPU
        foreach (self::CPU_SENSORS as $s) {
            try {
                $data = $this->fetchSensorMetrics($s['id'], ['Total'], $sdate, $edate);
                if ($data) {
                    $pid = $data['parent_id'];
                    $deviceMetrics[$pid] = array_merge($deviceMetrics[$pid] ?? [], [
                        'device_name' => $data['device_name'], 'ip' => $data['ip'], 
                        'group' => $data['group'], 'site' => $s['site'],
                        'cpu' => $data['metrics']['Total'],
                        'status' => $data['status']
                    ]);
                    $ok++;
                }
            } catch (\Exception $e) { $fail++; $notes[] = "CPU Sensor {$s['id']}: {$e->getMessage()}"; }
        }

        // 2. Process Memory
        foreach (self::MEMORY_SENSORS as $s) {
            try {
                $data = $this->fetchSensorMetrics($s['id'], ['Percent Available Memory'], $sdate, $edate);
                if ($data) {
                    $pid = $data['parent_id'];
                    $usedMem = 100 - $data['metrics']['Percent Available Memory'];
                    $deviceMetrics[$pid] = array_merge($deviceMetrics[$pid] ?? [], [
                        'device_name' => $data['device_name'], 'ip' => $data['ip'], 
                        'group' => $data['group'], 'site' => $s['site'],
                        'memory' => $usedMem,
                        'status' => $data['status']
                    ]);
                    $ok++;
                }
            } catch (\Exception $e) { $fail++; $notes[] = "Mem Sensor {$s['id']}: {$e->getMessage()}"; }
        }

        // 3. Process Disk
        foreach (self::DISK_SENSORS as $s) {
            try {
                // Use last value for Disk
                $data = $this->fetchSensorMetrics($s['id'], [$s['keyword']], $sdate, $edate, 3600, true);
                if ($data) {
                    $pid = $data['parent_id'];
                    if (!isset($deviceMetrics[$pid])) $deviceMetrics[$pid] = [
                        'device_name' => $data['device_name'], 'ip' => $data['ip'], 
                        'group' => $data['group'], 'site' => $s['site'],
                        'disks' => [], 'status' => $data['status']
                    ];
                    $deviceMetrics[$pid]['disks'][] = round($data['metrics'][$s['keyword']], 2);
                    $ok++;
                }
            } catch (\Exception $e) { $fail++; $notes[] = "Disk Sensor {$s['id']}: {$e->getMessage()}"; }
        }

        // 4. Process QNAP (Dual CPU/RAM)
        foreach (self::QNAP_SENSORS as $s) {
            try {
                $data = $this->fetchSensorMetrics($s['id'], ['CPU Usage', 'Percent Available Memory'], $sdate, $edate);
                if ($data) {
                    $pid = $data['parent_id'];
                    $metrics = [
                        'device_name' => $data['device_name'], 'ip' => $data['ip'], 
                        'group' => $data['group'], 'site' => $s['site'],
                        'status' => $data['status']
                    ];
                    if (isset($data['metrics']['CPU Usage'])) $metrics['cpu'] = $data['metrics']['CPU Usage'];
                    if (isset($data['metrics']['Percent Available Memory'])) $metrics['memory'] = 100 - $data['metrics']['Percent Available Memory'];
                    
                    $deviceMetrics[$pid] = array_merge($deviceMetrics[$pid] ?? [], $metrics);
                    $ok++;
                }
            } catch (\Exception $e) { $fail++; $notes[] = "QNAP Sensor {$s['id']}: {$e->getMessage()}"; }
        }

        // 5. Process QNAP Disk
        foreach (self::QNAP_DISK_SENSORS as $s) {
            try {
                // Use last value for Disk
                $data = $this->fetchSensorMetrics($s['id'], ['Free Space'], $sdate, $edate, 3600, true);
                if ($data) {
                    $pid = $data['parent_id'];
                    if (!isset($deviceMetrics[$pid])) $deviceMetrics[$pid] = [
                        'device_name' => $data['device_name'], 'ip' => $data['ip'], 
                        'group' => $data['group'], 'site' => $s['site'],
                        'disks' => [], 'status' => $data['status']
                    ];
                    $deviceMetrics[$pid]['disks'][] = round($data['metrics']['Free Space'], 2);
                    $ok++;
                }
            } catch (\Exception $e) { $fail++; $notes[] = "QNAP Disk Sensor {$s['id']}: {$e->getMessage()}"; }
        }

        // 6. Save consolidated metrics to DB
        foreach ($deviceMetrics as $pid => $data) {
            $device = ServerDevice::updateOrCreate(
                ['source' => 'prtg', 'source_id' => $pid],
                [
                    'device_name' => $data['device_name'], 
                    'ip_address' => $data['ip'],
                    'host_group' => $data['group'], 
                    'site' => $data['site'], 
                    'location' => $data['site'],
                    'last_status' => $data['status'] ?? 'UP',
                    'last_sync' => now(),
                ]
            );

            if (($data['status'] ?? '') === 'DOWN') {
                $this->autoCreateMaintenanceLog($device->id, $reportDate);
            }

            $resourceData = [];
            if (isset($data['cpu']))    $resourceData['cpu_usage_percent'] = round($data['cpu'], 2);
            if (isset($data['memory'])) $resourceData['memory_usage_percent'] = round($data['memory'], 2);
            if (!empty($data['disks'])) {
                $diskStrings = [];
                foreach ($data['disks'] as $i => $val) {
                    $diskStrings[] = "D" . ($i+1) . ": {$val}GB";
                }
                $resourceData['hdd_free_percent'] = implode(' | ', $diskStrings);
            }

            ServerResourceDaily::updateOrCreate(
                ['host_id' => $pid, 'report_date' => $reportDate],
                $resourceData
            );
        }

        $this->logFetch('resources', $reportDate, $ok, $fail, $notes, $triggeredBy, $isManual);
        return compact('ok', 'fail');
    }

    public function fetchTemperature(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $reportDate = $date->toDateString();
        $sdate = $date->copy()->startOfDay()->format('Y-m-d-H-i-s');
        $edate = $date->copy()->endOfDay()->format('Y-m-d-H-i-s');

        $ok = 0; $fail = 0; $notes = [];

        foreach (self::TEMPERATURE_SENSORS as $sensorId => $info) {
            try {
                $res = Http::timeout($this->timeout)->withoutVerifying()
                    ->get("{$this->baseUrl}/api/historicdata.json", [
                        'id' => $sensorId, 'avg' => $this->avgMinutes,
                        'sdate' => $sdate, 'edate' => $edate,
                        'apitoken' => $this->apiToken, 'usecaption' => 1
                    ]);

                if (!$res->ok()) throw new \Exception("HTTP Error " . $res->status());
                
                $hist = $res->json('histdata', []);
                $values = [];
                foreach ($hist as $row) {
                    if (($row['coverage'] ?? '') === '0 %') continue;
                    foreach ($row as $key => $val) {
                        if (str_contains(strtolower($key), strtolower($info['keyword'])) && !str_contains(strtolower($key), 'state')) {
                            $parsed = $this->parseValue($val);
                            if ($parsed !== null) $values[] = $parsed;
                        }
                    }
                }

                if ($values) {
                    $avg = array_sum($values) / count($values);
                    ServerTemperatureDaily::updateOrCreate(
                        ['sensor_id' => $sensorId, 'report_date' => $reportDate],
                        ['location' => $info['location'], 'description' => $info['description'], 'value_celsius' => round($avg, 2)]
                    );
                    $ok++;
                } else { $fail++; $notes[] = "Temp Sensor {$sensorId}: No data found"; }
            } catch (\Exception $e) { $fail++; $notes[] = "Temp Sensor {$sensorId}: " . $e->getMessage(); }
        }

        $this->logFetch('temperature', $reportDate, $ok, $fail, $notes, $triggeredBy, $isManual);
        return compact('ok', 'fail');
    }

    /**
     * Fetch multiple metrics for a single sensor.
     */
    private function fetchSensorMetrics(int $sensorId, array $keywords, string $sdate, string $edate, int $avg = null, bool $useLastValue = false): ?array
    {
        $avg = $avg ?? $this->avgMinutes;

        // 1. Get Sensor Info
        $infoRes = Http::timeout($this->timeout)->withoutVerifying()
            ->get("{$this->baseUrl}/api/table.json", [
                'content' => 'sensors', 'output' => 'json', 'columns' => 'objid,parentid,device,group,status',
                'filter_objid' => $sensorId, 'apitoken' => $this->apiToken
            ]);
        $sensorInfo = $infoRes->json('sensors.0');
        if (!$sensorInfo) return null;

        $parentId   = (int) $sensorInfo['parentid'];
        $deviceName = $sensorInfo['device'];
        $hostGroup  = $sensorInfo['group'];
        $status     = str_contains(strtolower($sensorInfo['status'] ?? ''), 'up') ? 'UP' : 'DOWN';

        // 2. Get Device Info
        $devRes = Http::timeout($this->timeout)->withoutVerifying()
            ->get("{$this->baseUrl}/api/table.json", [
                'content' => 'devices', 'output' => 'json', 'columns' => 'objid,host',
                'filter_objid' => $parentId, 'apitoken' => $this->apiToken
            ]);
        $ip = $devRes->json('devices.0.host', '-');

        // 3. Get History
        $histRes = Http::timeout($this->timeout)->withoutVerifying()
            ->get("{$this->baseUrl}/api/historicdata.json", [
                'id' => $sensorId, 'avg' => $avg, 'sdate' => $sdate, 'edate' => $edate,
                'apitoken' => $this->apiToken, 'usecaption' => 1
            ]);
        
        $hist = $histRes->json('histdata', []);
        if (empty($hist)) return null;

        $metricValues = [];
        foreach ($keywords as $kw) {
            $vals = [];
            foreach ($hist as $row) {
                if (($row['coverage'] ?? '') === '0 %') continue;
                
                // Flexible matching: find key that contains keyword (e.g. "Free Space" matches "Free Space (GB)")
                $foundKey = null;
                foreach (array_keys($row) as $key) {
                    if (str_contains(strtolower($key), strtolower($kw))) {
                        $foundKey = $key;
                        break;
                    }
                }

                if ($foundKey) {
                    $val = $row[$foundKey] ?? null;
                    $parsed = $this->parseValue($val);
                    if ($parsed !== null) $vals[] = $parsed;
                }
            }
            
            if ($vals) {
                $metricValues[$kw] = $useLastValue ? end($vals) : (array_sum($vals) / count($vals));
            }
        }

        if (empty($metricValues)) return null;

        return [
            'metrics'     => $metricValues,
            'parent_id'   => $parentId,
            'device_name' => $deviceName,
            'ip'          => $ip,
            'group'       => $hostGroup,
            'status'      => $status
        ];
    }

    private function updateDeviceAndDaily(int $sensorId, string $site, string $date, array $metrics): void
    {
        // For CPU/RAM/QNAP, source_id is the sensor_id
        $infoRes = Http::timeout($this->timeout)->withoutVerifying()
            ->get("{$this->baseUrl}/api/table.json", [
                'content' => 'sensors', 'output' => 'json', 'columns' => 'objid,parentid,device,group,status',
                'filter_objid' => $sensorId, 'apitoken' => $this->apiToken
            ]);
        $sensorInfo = $infoRes->json('sensors.0');
        if (!$sensorInfo) return;

        $parentId   = (int) $sensorInfo['parentid'];
        $deviceName = $sensorInfo['device'];
        $hostGroup  = $sensorInfo['group'];
        $status     = str_contains(strtolower($sensorInfo['status'] ?? ''), 'up') ? 'UP' : 'DOWN';

        $devRes = Http::timeout($this->timeout)->withoutVerifying()
            ->get("{$this->baseUrl}/api/table.json", [
                'content' => 'devices', 'output' => 'json', 'columns' => 'objid,host',
                'filter_objid' => $parentId, 'apitoken' => $this->apiToken
            ]);
        $ip = $devRes->json('devices.0.host', '-');

        $device = ServerDevice::updateOrCreate(
            ['source' => 'prtg', 'source_id' => $sensorId],
            [
                'device_name' => $deviceName, 'ip_address' => $ip,
                'host_group' => $hostGroup, 'site' => $site, 'location' => $site,
                'last_status' => $status, 'last_sync' => now(),
            ]
        );

        if ($status === 'DOWN') {
            $this->autoCreateMaintenanceLog($device->id, $date);
        }

        $data = [];
        if (isset($metrics['cpu']))    $data['cpu_usage_percent']    = round($metrics['cpu'], 2);
        if (isset($metrics['memory'])) $data['memory_usage_percent'] = round($metrics['memory'], 2);

        ServerResourceDaily::updateOrCreate(
            ['host_id' => $sensorId, 'report_date' => $date],
            $data
        );
    }

    private function logFetch(string $type, string $date, int $ok, int $fail, array $notes, ?int $triggeredBy, bool $isManual): void
    {
        $status = $fail === 0 ? 'success' : ($ok > 0 ? 'partial' : 'failed');
        ServerFetchLog::updateOrCreate(
            [
                'fetch_date' => $date,
                'group_name' => ucfirst($type),
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
    }

    private function autoCreateMaintenanceLog(int $deviceId, string $date): void
    {
        $device = ServerDevice::find($deviceId);
        if ($device?->is_excluded) return;

        // Block creation if ANY open ticket already exists for this device (any date).
        $exists = \App\Models\ServerMaintenanceLog::where('device_id', $deviceId)
            ->where('status', 'open')
            ->exists();

        if (!$exists) {
            \App\Models\ServerMaintenanceLog::create([
                'device_id'  => $deviceId,
                'status'     => 'open',
                'started_at' => now(),
                'event_type' => 'auto_detected',
                'notes'      => "Sensor down terdeteksi pada " . now()->toDateTimeString() . ". Dibuat otomatis oleh sistem.",
                'is_auto'    => true,
            ]);
        }
    }

    private function parseValue($val): ?float
    {
        if ($val === null || $val === '') return null;
        if (is_numeric($val)) return (float) $val;

        // Strip units and symbols
        $str = (string)$val;
        $str = str_replace(['%', 'GB', 'MB', 'KB', ' '], '', $str);
        $str = trim($str);
        
        // Handle "< 1" or similar
        if (str_starts_with($str, '<')) {
            $num = trim(substr($str, 1));
            return is_numeric($num) ? (float)$num - 0.1 : 0.0;
        }

        return is_numeric($str) ? (float)$str : null;
    }
}
