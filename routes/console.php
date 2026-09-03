<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:db-audit {--benchmark : Run read/write micro-benchmarks in a rollback transaction}', function () {
    $connection = DB::connection();
    $driver = $connection->getDriverName();
    $hasCompletionFlag = Schema::hasColumn('stbs', 'is_completed');

    $this->info('Connection: ' . $connection->getName());
    $this->line('Driver: ' . $driver);

    $plans = [
        'auth_logs_filtered' => "SELECT * FROM auth_logs WHERE event = 'login' AND status = 'success' AND created_at >= '2026-01-01 00:00:00' ORDER BY created_at DESC LIMIT 20",
        'stbs_completed' => $hasCompletionFlag
            ? "SELECT * FROM stbs WHERE is_completed = 1 ORDER BY created_at DESC LIMIT 10"
            : "SELECT * FROM stbs WHERE completed_at IS NOT NULL AND completed_pdf_path IS NOT NULL ORDER BY created_at DESC LIMIT 10",
        'stbs_pending' => $hasCompletionFlag
            ? "SELECT * FROM stbs WHERE is_completed = 0 ORDER BY created_at DESC LIMIT 10"
            : "SELECT * FROM stbs WHERE completed_at IS NULL OR completed_pdf_path IS NULL ORDER BY created_at DESC LIMIT 10",
        'asset_stock_history' => "SELECT * FROM asset_stock_histories WHERE asset_type = 'accessories' AND asset_id = 1 ORDER BY purchase_date DESC, id DESC LIMIT 20",
        'users_lookup' => "SELECT * FROM users WHERE email = 'test@example.com' OR username = 'test@example.com' OR snipeit_username = 'test@example.com' LIMIT 1",
    ];

    $this->newLine();
    $this->info('Query Plans');

    $summaries = [];

    foreach ($plans as $name => $sql) {
        $this->line('');
        $this->comment($name);

        $explainSql = $driver === 'sqlite'
            ? 'EXPLAIN QUERY PLAN ' . $sql
            : 'EXPLAIN ' . $sql;

        $rows = $connection->select($explainSql);
        $details = collect($rows)
            ->map(fn ($row) => (string) ((array) $row)['detail'])
            ->values();

        foreach ($rows as $row) {
            $this->line(json_encode((array) $row, JSON_UNESCAPED_SLASHES));
        }

        $hasTempSort = $details->contains(fn (string $detail) => str_contains($detail, 'TEMP B-TREE'));
        $hasScan = $details->contains(fn (string $detail) => str_contains($detail, 'SCAN '));
        $hasMultiIndexOr = $details->contains(fn (string $detail) => str_contains($detail, 'MULTI-INDEX OR'));
        $summary = collect();

        if ($hasTempSort) {
            $summary->push('temp-sort');
        }

        if ($hasScan) {
            $summary->push('scan');
        }

        if ($hasMultiIndexOr) {
            $summary->push('multi-index-or');
        }

        if ($summary->isEmpty()) {
            $summary->push('index-friendly');
        }

        $summaries[] = [
            'name' => $name,
            'severity' => $hasTempSort || $hasScan ? 'warn' : 'ok',
            'summary' => $summary->implode(', '),
        ];
    }

    $this->newLine();
    $this->info('Plan Summary');

    foreach ($summaries as $item) {
        $prefix = $item['severity'] === 'warn' ? '[warn]' : '[ok]';
        $this->line($prefix . ' ' . $item['name'] . ' => ' . $item['summary']);
    }

    if (!$this->option('benchmark')) {
        return;
    }

    $userId = (int) ($connection->table('users')->value('id') ?? 1);
    $timestamp = now();
    $results = [];
    $measure = function (callable $callback, int $iterations = 1): float {
        $start = hrtime(true);

        for ($index = 0; $index < $iterations; $index++) {
            $callback();
        }

        return round((hrtime(true) - $start) / 1_000_000, 2);
    };

    $connection->beginTransaction();

    try {
        $authLogRows = [];

        for ($index = 0; $index < 2000; $index++) {
            $authLogRows[] = [
                'user_id' => $userId,
                'identifier' => 'bench-user-' . $index,
                'event' => $index % 2 === 0 ? 'login' : 'logout',
                'status' => $index % 3 === 0 ? 'success' : 'failed',
                'ip_address' => '10.0.0.' . ($index % 255),
                'user_agent' => 'bench-agent',
                'meta' => json_encode(['index' => $index]),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $results['write_auth_logs_2000_rows_ms'] = $measure(function () use ($connection, $authLogRows) {
            $connection->table('auth_logs')->insert($authLogRows);
        });

        $results['read_auth_logs_filtered_x200_ms'] = $measure(function () use ($connection) {
            $connection->table('auth_logs')
                ->where('event', 'login')
                ->where('status', 'success')
                ->where('created_at', '>=', '2026-01-01 00:00:00')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();
        }, 200);

        $stockRows = [];

        for ($index = 0; $index < 2000; $index++) {
            $stockRows[] = [
                'asset_type' => 'accessories',
                'asset_id' => 1,
                'qty' => 1,
                'po_number' => 'PO-' . $index,
                'purchase_date' => now()->subDays($index % 365)->toDateString(),
                'document_path' => null,
                'notes' => 'bench',
                'created_by' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        $results['write_stock_history_2000_rows_ms'] = $measure(function () use ($connection, $stockRows) {
            $connection->table('asset_stock_histories')->insert($stockRows);
        });

        $results['read_stock_history_x200_ms'] = $measure(function () use ($connection) {
            $connection->table('asset_stock_histories')
                ->where('asset_type', 'accessories')
                ->where('asset_id', 1)
                ->orderByDesc('purchase_date')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }, 200);

        $results['read_user_lookup_x500_ms'] = $measure(function () use ($connection) {
            $connection->table('users')
                ->where('email', 'test@example.com')
                ->orWhere('username', 'test@example.com')
                ->orWhere('snipeit_username', 'test@example.com')
                ->limit(1)
                ->get();
        }, 500);

        $results['read_stbs_completed_x500_ms'] = $measure(function () use ($connection, $hasCompletionFlag) {
            $query = $connection->table('stbs')->orderByDesc('created_at')->limit(10);

            if ($hasCompletionFlag) {
                $query->where('is_completed', true);
            } else {
                $query->whereNotNull('completed_at')->whereNotNull('completed_pdf_path');
            }

            $query->get();
        }, 500);

        $results['read_stbs_pending_x500_ms'] = $measure(function () use ($connection, $hasCompletionFlag) {
            $query = $connection->table('stbs')->orderByDesc('created_at')->limit(10);

            if ($hasCompletionFlag) {
                $query->where('is_completed', false);
            } else {
                $query->where(function ($pendingQuery) {
                    $pendingQuery->whereNull('completed_at')->orWhereNull('completed_pdf_path');
                });
            }

            $query->get();
        }, 500);

        $this->newLine();
        $this->info('Benchmarks');

        foreach ($results as $name => $value) {
            $this->line($name . '=' . $value . ' ms');
        }

        $this->newLine();
        $this->info('Benchmark Summary');
        $this->line('auth_logs filtered read avg=' . round($results['read_auth_logs_filtered_x200_ms'] / 200, 3) . ' ms/op');
        $this->line('asset_stock_history read avg=' . round($results['read_stock_history_x200_ms'] / 200, 3) . ' ms/op');
        $this->line('user lookup avg=' . round($results['read_user_lookup_x500_ms'] / 500, 3) . ' ms/op');
        $this->line('stbs completed read avg=' . round($results['read_stbs_completed_x500_ms'] / 500, 3) . ' ms/op');
        $this->line('stbs pending read avg=' . round($results['read_stbs_pending_x500_ms'] / 500, 3) . ' ms/op');
    } finally {
        $connection->rollBack();
    }
})->purpose('Inspect DB query plans and benchmark representative reads/writes');
