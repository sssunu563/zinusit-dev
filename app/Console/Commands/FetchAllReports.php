<?php

namespace App\Console\Commands;

use App\Models\ActionLog;
use App\Services\CctvMonitorService;
use App\Services\NetworkMonitorService;
use App\Services\PrtgService;
use App\Services\ServerMonitorService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchAllReports extends Command
{
    protected $signature   = 'reports:fetch-all {--date= : Tanggal target (Y-m-d), default kemarin}';
    protected $description = 'Fetch semua report secara berurutan: CCTV Operation → Bandwidth → Network Uptime → Server Operation';

    public function handle(
        CctvMonitorService  $cctvService,
        PrtgService         $prtgService,
        NetworkMonitorService $networkService,
        ServerMonitorService $serverService,
    ): int {
        $dateStr = $this->option('date');
        $date    = $dateStr ? Carbon::parse($dateStr) : Carbon::yesterday();

        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────┐');
        $this->line('│  AUTO FETCH ALL REPORTS — ' . $date->toDateString() . '          │');
        $this->line('└─────────────────────────────────────────────────────┘');
        $this->newLine();

        $overallOk   = 0;
        $overallFail = 0;
        $startedAt   = now();

        // ── 1. CCTV Operation ─────────────────────────────────────────────────
        $this->info('[1/4] CCTV Operation...');
        try {
            $results   = $cctvService->fetchAll($date, null, false);
            $ok        = array_sum(array_column($results, 'ok'));
            $fail      = array_sum(array_column($results, 'fail'));
            $overallOk   += $ok;
            $overallFail += $fail;

            foreach ($results as $source => $r) {
                $icon = $r['status'] === 'success' ? '✓' : ($r['status'] === 'partial' ? '~' : '✗');
                $this->line("    [{$icon}] {$source}: OK={$r['ok']}, Fail={$r['fail']}");
            }
            $this->line("    → Total: {$ok} OK, {$fail} gagal");

            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch',
                'item_type'   => 'CctvOperation',
                'item_id'     => null,
                'note'        => "Auto fetch CCTV Operation: {$ok} OK, {$fail} gagal. Tanggal: {$date->toDateString()}",
                'log_meta'    => ['date' => $date->toDateString(), 'results' => $results],
            ]);

            Log::info("[FetchAllReports] CCTV Operation selesai — OK={$ok}, Fail={$fail}");
        } catch (\Throwable $e) {
            $overallFail++;
            $this->error("    ✗ CCTV Operation gagal: " . $e->getMessage());
            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch_error',
                'item_type'   => 'CctvOperation',
                'item_id'     => null,
                'note'        => "Auto fetch CCTV Operation GAGAL: " . $e->getMessage(),
                'log_meta'    => ['date' => $date->toDateString(), 'error' => $e->getMessage()],
            ]);
            Log::error("[FetchAllReports] CCTV Operation error: " . $e->getMessage());
        }
        $this->newLine();

        // ── 2. Bandwidth (PRTG) ───────────────────────────────────────────────
        $this->info('[2/4] Bandwidth (PRTG)...');
        try {
            $result      = $prtgService->fetchForDate($date, null, false);
            $ok          = $result['ok'];
            $fail        = $result['fail'];
            $overallOk   += $ok;
            $overallFail += $fail;

            $icon = $result['status'] === 'success' ? '✓' : ($result['status'] === 'partial' ? '~' : '✗');
            $this->line("    [{$icon}] Status={$result['status']}, OK={$ok}, Fail={$fail}");
            foreach (array_slice($result['notes'] ?? [], 0, 3) as $note) {
                $this->warn("      → {$note}");
            }

            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch',
                'item_type'   => 'Bandwidth',
                'item_id'     => null,
                'note'        => "Auto fetch Bandwidth: {$ok} OK, {$fail} gagal. Tanggal: {$date->toDateString()}",
                'log_meta'    => ['date' => $date->toDateString(), 'result' => $result],
            ]);

            Log::info("[FetchAllReports] Bandwidth selesai — OK={$ok}, Fail={$fail}");
        } catch (\Throwable $e) {
            $overallFail++;
            $this->error("    ✗ Bandwidth gagal: " . $e->getMessage());
            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch_error',
                'item_type'   => 'Bandwidth',
                'item_id'     => null,
                'note'        => "Auto fetch Bandwidth GAGAL: " . $e->getMessage(),
                'log_meta'    => ['date' => $date->toDateString(), 'error' => $e->getMessage()],
            ]);
            Log::error("[FetchAllReports] Bandwidth error: " . $e->getMessage());
        }
        $this->newLine();

        // ── 3. Network Uptime (PRTG + Zabbix) ────────────────────────────────
        $this->info('[3/4] Network Uptime (PRTG + Zabbix)...');
        try {
            $results     = $networkService->fetchAll($date, null, false);
            $ok          = array_sum(array_column($results, 'ok'));
            $fail        = array_sum(array_column($results, 'fail'));
            $overallOk   += $ok;
            $overallFail += $fail;

            foreach ($results as $source => $r) {
                $icon = $r['status'] === 'success' ? '✓' : ($r['status'] === 'partial' ? '~' : '✗');
                $this->line("    [{$icon}] {$source}: OK={$r['ok']}, Fail={$r['fail']}");
            }
            $this->line("    → Total: {$ok} OK, {$fail} gagal");

            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch',
                'item_type'   => 'NetworkUptime',
                'item_id'     => null,
                'note'        => "Auto fetch Network Uptime: {$ok} OK, {$fail} gagal. Tanggal: {$date->toDateString()}",
                'log_meta'    => ['date' => $date->toDateString(), 'results' => $results],
            ]);

            Log::info("[FetchAllReports] Network Uptime selesai — OK={$ok}, Fail={$fail}");
        } catch (\Throwable $e) {
            $overallFail++;
            $this->error("    ✗ Network Uptime gagal: " . $e->getMessage());
            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch_error',
                'item_type'   => 'NetworkUptime',
                'item_id'     => null,
                'note'        => "Auto fetch Network Uptime GAGAL: " . $e->getMessage(),
                'log_meta'    => ['date' => $date->toDateString(), 'error' => $e->getMessage()],
            ]);
            Log::error("[FetchAllReports] Network Uptime error: " . $e->getMessage());
        }
        $this->newLine();

        // ── 4. Server Operation (CPU/RAM/Disk + Suhu) ─────────────────────────
        $this->info('[4/4] Server Operation (Resources + Temperature)...');
        try {
            $results     = $serverService->fetchAll($date, null, false);
            $ok          = ($results['resources']['ok'] ?? 0) + ($results['temperature']['ok'] ?? 0);
            $fail        = ($results['resources']['fail'] ?? 0) + ($results['temperature']['fail'] ?? 0);
            $overallOk   += $ok;
            $overallFail += $fail;

            $this->line("    [✓] Resources: OK={$results['resources']['ok']}, Fail={$results['resources']['fail']}");
            $this->line("    [✓] Temperature: OK={$results['temperature']['ok']}, Fail={$results['temperature']['fail']}");

            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch',
                'item_type'   => 'ServerOperation',
                'item_id'     => null,
                'note'        => "Auto fetch Server Operation: {$ok} OK, {$fail} gagal. Tanggal: {$date->toDateString()}",
                'log_meta'    => ['date' => $date->toDateString(), 'results' => $results],
            ]);

            Log::info("[FetchAllReports] Server Operation selesai — OK={$ok}, Fail={$fail}");
        } catch (\Throwable $e) {
            $overallFail++;
            $this->error("    ✗ Server Operation gagal: " . $e->getMessage());
            ActionLog::create([
                'user_id'     => null,
                'action_type' => 'auto_fetch_error',
                'item_type'   => 'ServerOperation',
                'item_id'     => null,
                'note'        => "Auto fetch Server Operation GAGAL: " . $e->getMessage(),
                'log_meta'    => ['date' => $date->toDateString(), 'error' => $e->getMessage()],
            ]);
            Log::error("[FetchAllReports] Server Operation error: " . $e->getMessage());
        }
        $this->newLine();

        // ── Summary ───────────────────────────────────────────────────────────
        $duration = now()->diffInSeconds($startedAt);
        $this->line('┌─────────────────────────────────────────────────────┐');
        $this->line("│  SELESAI — {$overallOk} OK, {$overallFail} gagal, durasi {$duration}s");
        $this->line('└─────────────────────────────────────────────────────┘');

        ActionLog::create([
            'user_id'     => null,
            'action_type' => 'auto_fetch',
            'item_type'   => 'AllReports',
            'item_id'     => null,
            'note'        => "Auto fetch semua report selesai: {$overallOk} OK, {$overallFail} gagal. Tanggal: {$date->toDateString()}. Durasi: {$duration}s",
            'log_meta'    => ['date' => $date->toDateString(), 'total_ok' => $overallOk, 'total_fail' => $overallFail, 'duration_seconds' => $duration],
        ]);

        Log::info("[FetchAllReports] Semua report selesai — Total OK={$overallOk}, Fail={$overallFail}, Durasi={$duration}s");

        return $overallFail > 0 && $overallOk === 0 ? self::FAILURE : self::SUCCESS;
    }
}
