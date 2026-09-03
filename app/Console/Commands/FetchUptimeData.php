<?php

namespace App\Console\Commands;

use App\Services\NetworkMonitorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FetchUptimeData extends Command
{
    protected $signature   = 'network:fetch {--date= : Tanggal spesifik (Y-m-d), default kemarin}';
    protected $description = 'Fetch uptime data dari PRTG + semua Zabbix dan simpan ke database';

    public function handle(NetworkMonitorService $service): int
    {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->info("Fetching network uptime untuk: {$date->toDateString()}");

        $results = $service->fetchAll($date, null, false);

        foreach ($results as $source => $r) {
            $icon = $r['status'] === 'success' ? '✓' : ($r['status'] === 'partial' ? '~' : '✗');
            $this->line("  [{$icon}] {$source}: OK={$r['ok']}, Fail={$r['fail']}, Status={$r['status']}");
            if (!empty($r['notes'])) {
                foreach (array_slice($r['notes'], 0, 3) as $note) {
                    $this->warn("      → {$note}");
                }
            }
        }

        $totalOk   = array_sum(array_column($results, 'ok'));
        $totalFail = array_sum(array_column($results, 'fail'));
        $this->info("Total: {$totalOk} OK, {$totalFail} gagal.");

        return self::SUCCESS;
    }
}
