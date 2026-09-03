<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ExportPrtgDevicesCsv extends Command
{
    protected $signature   = 'prtg:export-csv {--output= : Path file output (default: storage/app/prtg_devices.csv)}';
    protected $description = 'Export semua device dari PRTG ke CSV untuk persiapan import ke Snipe-IT';

    // Mapping PRTG probe/group → Location label
    private const LOCATION_MAP = [
        'Parung'    => ['F1' => 'ZGI BGR F1', 'R3' => 'ZGI BGR R3'],
        'Karawang'  => ['default' => 'ZGI KRW F2'],
        'Tangerang' => ['default' => 'ZDI TGR F3'],
    ];

    private const PROBES = ['Parung', 'Karawang', 'Tangerang'];

    public function handle(): int
    {
        $baseUrl  = rtrim((string) config('services.prtg.url', ''), '/');
        $token    = (string) config('services.prtg.api_token', '');
        $timeout  = (int) config('services.prtg.timeout', 30);
        $output   = $this->option('output') ?? storage_path('app/prtg_devices.csv');

        if (!$baseUrl || !$token) {
            $this->error('PRTG_URL atau PRTG_API_TOKEN belum dikonfigurasi di .env');
            return self::FAILURE;
        }

        $this->info("Fetching devices dari PRTG: {$baseUrl}");

        $allRows = [];

        foreach (self::PROBES as $probe) {
            $this->info("  → Probe: {$probe}");

            // Fetch devices
            try {
                $devRes = Http::timeout($timeout)->withoutVerifying()
                    ->get("{$baseUrl}/api/table.json", [
                        'content'      => 'devices',
                        'output'       => 'json',
                        'columns'      => 'objid,device,host,group,probe,location',
                        'filter_probe' => $probe,
                        'apitoken'     => $token,
                    ]);

                $devices = $devRes->json('devices', []);
                $this->info("     Devices: " . count($devices));

                // Build device map
                $deviceMap = [];
                foreach ($devices as $d) {
                    $deviceMap[(int) $d['objid']] = [
                        'name'     => $d['device']   ?? '-',
                        'ip'       => $d['host']      ?? '-',
                        'group'    => $d['group']     ?? '-',
                        'probe'    => $d['probe']     ?? $probe,
                        'location' => $d['location']  ?? '',
                    ];
                }

                // Fetch ping sensors to get sensor IDs
                $sensorRes = Http::timeout($timeout)->withoutVerifying()
                    ->get("{$baseUrl}/api/table.json", [
                        'content'       => 'sensors',
                        'output'        => 'json',
                        'columns'       => 'objid,device,group,parentid,sensor,status,lastvalue',
                        'filter_sensor' => 'Ping',
                        'filter_probe'  => $probe,
                        'apitoken'      => $token,
                    ]);

                $sensors = $sensorRes->json('sensors', []);
                $this->info("     Ping sensors: " . count($sensors));

                foreach ($sensors as $s) {
                    $sensorId = (int) $s['objid'];
                    $parentId = (int) $s['parentid'];
                    $dev      = $deviceMap[$parentId] ?? [];

                    $devName  = $dev['name']  ?? ($s['device'] ?? '-');
                    $ip       = $dev['ip']    ?? '-';
                    $group    = $dev['group'] ?? ($s['group'] ?? '-');
                    $location = $this->resolveLocation($probe, $group, $dev['location'] ?? '');
                    $status   = strtolower($s['status'] ?? '');
                    $statusLabel = str_contains($status, 'up') ? 'UP' : 'DOWN';

                    $allRows[] = [
                        'sensor_id'   => $sensorId,
                        'device_id'   => $parentId,
                        'device_name' => $devName,
                        'ip_address'  => $ip,
                        'host_group'  => $group,
                        'probe'       => $probe,
                        'location'    => $location,
                        'last_status' => $statusLabel,
                        // Snipe-IT fields to fill
                        'asset_tag'   => '',
                        'brand'       => '',
                        'model'       => '',
                        'category'    => '',
                        'type'        => '',
                        'notes'       => '',
                    ];
                }
            } catch (\Throwable $e) {
                $this->warn("  ✗ Error probe {$probe}: " . $e->getMessage());
            }
        }

        if (empty($allRows)) {
            $this->error('Tidak ada data yang berhasil diambil dari PRTG.');
            return self::FAILURE;
        }

        // Write CSV
        $fp = fopen($output, 'w');

        // Header
        fputcsv($fp, [
            'Sensor ID',
            'Device ID',
            'Device Name',
            'IP Address',
            'Host Group',
            'Probe',
            'Location',
            'Last Status',
            '--- SNIPE-IT (isi manual) ---',
            'Asset Tag',
            'Brand',
            'Model',
            'Category',
            'Type',
            'Notes',
        ]);

        // Sort by location, then group, then device name
        usort($allRows, fn ($a, $b) =>
            strcmp($a['location'] . $a['host_group'] . $a['device_name'],
                   $b['location'] . $b['host_group'] . $b['device_name'])
        );

        foreach ($allRows as $row) {
            fputcsv($fp, [
                $row['sensor_id'],
                $row['device_id'],
                $row['device_name'],
                $row['ip_address'],
                $row['host_group'],
                $row['probe'],
                $row['location'],
                $row['last_status'],
                '',  // separator column
                $row['asset_tag'],
                $row['brand'],
                $row['model'],
                $row['category'],
                $row['type'],
                $row['notes'],
            ]);
        }

        fclose($fp);

        $this->info('');
        $this->info("✓ CSV berhasil dibuat: {$output}");
        $this->info("  Total device: " . count($allRows));
        $this->info('');
        $this->info('Langkah selanjutnya:');
        $this->info('  1. Buka file CSV di Excel');
        $this->info('  2. Isi kolom Asset Tag, Brand, Model, Category, Type');
        $this->info('  3. Simpan dan kirim ke tim untuk import ke Snipe-IT');

        return self::SUCCESS;
    }

    private function resolveLocation(string $probe, string $group, string $locationField): string
    {
        // Try to detect F1 vs R3 for Parung probe
        if ($probe === 'Parung') {
            $combined = strtolower($group . ' ' . $locationField);
            if (str_contains($combined, 'r3') || str_contains($combined, 'r-3')) {
                return self::LOCATION_MAP['Parung']['R3'];
            }
            return self::LOCATION_MAP['Parung']['F1'];
        }

        return self::LOCATION_MAP[$probe]['default'] ?? $probe;
    }
}
