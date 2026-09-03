<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bandwidth_daily', function (Blueprint $table) {
            // summary() query: WHERE report_date BETWEEN x AND y GROUP BY report_date, location, provider, description
            // Composite index covers the GROUP BY + WHERE in one scan
            if (!$this->indexExists('bandwidth_daily', 'bw_daily_summary_idx')) {
                $table->index(
                    ['report_date', 'location', 'provider', 'description'],
                    'bw_daily_summary_idx'
                );
            }

            // data() query: WHERE report_date BETWEEN x AND y ORDER BY report_date, location, provider
            if (!$this->indexExists('bandwidth_daily', 'bw_daily_data_idx')) {
                $table->index(
                    ['report_date', 'location', 'provider'],
                    'bw_daily_data_idx'
                );
            }
        });

        Schema::table('bandwidth_fetch_logs', function (Blueprint $table) {
            // logs() query: ORDER BY fetch_date DESC, id DESC LIMIT 100
            if (!$this->indexExists('bandwidth_fetch_logs', 'bw_logs_order_idx')) {
                $table->index(['fetch_date', 'id'], 'bw_logs_order_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bandwidth_daily', function (Blueprint $table) {
            $table->dropIndex('bw_daily_summary_idx');
            $table->dropIndex('bw_daily_data_idx');
        });

        Schema::table('bandwidth_fetch_logs', function (Blueprint $table) {
            $table->dropIndex('bw_logs_order_idx');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            return match (\Illuminate\Support\Facades\DB::getDriverName()) {
                'mysql'  => collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))
                    ->contains(fn ($i) => ($i->Key_name ?? null) === $indexName),
                'sqlite' => collect(\Illuminate\Support\Facades\DB::select("PRAGMA index_list('{$table}')"))
                    ->contains(fn ($i) => ($i->name ?? null) === $indexName),
                default  => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }
};
