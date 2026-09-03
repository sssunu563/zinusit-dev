<?php

namespace App\Services;

use App\Models\CctvDaily;
use App\Models\CctvFetchLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CctvPrtgService
{
    // Sensor map: sensor_id => [location, provider]
    private const SENSOR_MAP = [

        // Format: 'sensor_id' => ['Nama Lokasi', 'Provider/Tipe'],
        // Contoh: '1234' => ['F1 Bogor', 'CCTV'],
    ];

    private string $baseUrl;
    private string $apiToken;
    private int    $avgMinutes;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl    = rtrim((string) config('services.prtg.url', ''), '/');
        $this->apiToken   = (string) config('services.prtg.api_token', '');
        $this->avgMinutes = (int) config('services.prtg.avg_minutes', 300);
        $this->timeout    = (int) config('services.prtg.timeout', 15);

        Log::debug('PrtgService init', [
            'baseUrl'  => $this->baseUrl,
            'token'    => substr($this->apiToken, 0, 10) . '...',
            'env_url'  => env('PRTG_URL'),
            'env_token'=> substr((string) env('PRTG_API_TOKEN'), 0, 10) . '...',
        ]);
    }

    /**
     * Fetch and store bandwidth data for a specific date.
     * Returns a summary array.
     */
    public function fetchForDate(Carbon $date, ?int $triggeredBy = null, bool $isManual = false): array
    {
        $sdate = $date->copy()->startOfDay()->format('Y-m-d-H-i-s');
        $edate = $date->copy()->endOfDay()->format('Y-m-d-H-i-s');
        $reportDate = $date->toDateString();

        $ok   = 0;
        $fail = 0;
        $notes = [];

        foreach (self::SENSOR_MAP as $sensorId => [$location, $provider]) {
            try {
                $result = $this->fetchSensor($sensorId, $sdate, $edate);

                if ($result === null) {
                    $fail++;
                    $notes[] = "Sensor {$sensorId} ({$location}/{$provider}): no data";
                    continue;
                }

                // Upsert Download
                CctvDaily::updateOrCreate(
                    ['sensor_id' => $sensorId, 'description' => 'Download (Mbps)', 'report_date' => $reportDate],
                    ['location' => $location, 'provider' => $provider, 'value_mbps' => $result['download']]
                );

                // Upsert Upload
                CctvDaily::updateOrCreate(
                    ['sensor_id' => $sensorId, 'description' => 'Upload (Mbps)', 'report_date' => $reportDate],
                    ['location' => $location, 'provider' => $provider, 'value_mbps' => $result['upload']]
                );

                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                $notes[] = "Sensor {$sensorId}: " . $e->getMessage();
                Log::warning("PRTG fetch error sensor {$sensorId}: " . $e->getMessage());
            }
        }

        $status = match (true) {
            $fail === 0                          => 'success',
            $ok > 0 && $fail > 0                 => 'partial',
            default                              => 'failed',
        };

        CctvFetchLog::updateOrCreate(
            [
                'fetch_date' => $reportDate,
            ],
            [
                'status'       => $status,
                'sensors_ok'   => $ok,
                'sensors_fail' => $fail,
                'notes'        => $notes ? implode("\n", $notes) : null,
                'triggered_by' => $triggeredBy,
                'is_manual'    => $isManual,
            ]
        );

        return compact('ok', 'fail', 'status', 'notes');
    }

    /**
     * Fetch a single sensor's max download/upload for a date range.
     */
    private function fetchSensor(string $sensorId, string $sdate, string $edate): ?array
    {
        $response = Http::timeout($this->timeout)
            ->withoutVerifying()
            ->get("{$this->baseUrl}/api/historicdata.json", [
                'id'         => $sensorId,
                'avg'        => $this->avgMinutes,
                'sdate'      => $sdate,
                'edate'      => $edate,
                'apitoken'   => $this->apiToken,
                'usecaption' => '1',
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $histdata = $response->json('histdata', []);

        $trafficIn  = [];
        $trafficOut = [];

        foreach ($histdata as $row) {
            if (($row['coverage'] ?? '') === '0 %') {
                continue;
            }

            foreach ($row as $key => $value) {
                if (!$value) continue;
                $keyLower = strtolower((string) $key);

                if (str_contains($keyLower, 'traffic in') && str_contains($keyLower, 'speed')) {
                    $trafficIn[] = (float) $value;
                }
                if (str_contains($keyLower, 'traffic out') && str_contains($keyLower, 'speed')) {
                    $trafficOut[] = (float) $value;
                }
            }
        }

        if (empty($trafficIn)) {
            return null;
        }

        return [
            'download' => round(max($trafficIn)  / 125000, 2),
            'upload'   => empty($trafficOut) ? 0.0 : round(max($trafficOut) / 125000, 2),
        ];
    }

    /**
     * Get all unique locations from sensor map.
     */
    public static function locations(): array
    {
        return array_values(array_unique(array_column(self::SENSOR_MAP, 0)));
    }

    /**
     * Get sensor map for frontend reference.
     */
    public static function sensorMap(): array
    {
        return self::SENSOR_MAP;
    }
}
