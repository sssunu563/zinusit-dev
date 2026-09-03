<?php

namespace App\Console\Commands;

use App\Services\PrtgService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchBandwidthData extends Command
{
    protected $signature   = 'bandwidth:fetch {--date= : Tanggal spesifik (Y-m-d), default kemarin}';
    protected $description = 'Fetch bandwidth data dari PRTG dan simpan ke database';

    public function handle(PrtgService $prtg): int
    {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->info("Fetching bandwidth data untuk: {$date->toDateString()}");

        $result = $prtg->fetchForDate($date, null, false);

        $this->info("Selesai — OK: {$result['ok']}, Gagal: {$result['fail']}, Status: {$result['status']}");

        if (!empty($result['notes'])) {
            foreach ($result['notes'] as $note) {
                $this->warn("  · {$note}");
            }
        }

        return $result['fail'] > 0 && $result['ok'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
