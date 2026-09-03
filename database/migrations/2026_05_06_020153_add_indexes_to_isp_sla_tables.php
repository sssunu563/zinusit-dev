<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        try {
            return match (\Illuminate\Support\Facades\DB::getDriverName()) {
                'mysql'  => collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))
                    ->contains(fn ($i) => ($i->Key_name ?? null) === $index),
                'sqlite' => collect(\Illuminate\Support\Facades\DB::select("PRAGMA index_list('{$table}')"))
                    ->contains(fn ($i) => ($i->name ?? null) === $index),
                default  => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        if (Schema::hasTable('isp_sla_contracts')) {
            Schema::table('isp_sla_contracts', function (Blueprint $table) {
                if (!$this->indexExists('isp_sla_contracts', 'isp_contracts_location_idx'))
                    $table->index(['location', 'fct'], 'isp_contracts_location_idx');
                if (!$this->indexExists('isp_sla_contracts', 'isp_contracts_active_idx'))
                    $table->index('is_active', 'isp_contracts_active_idx');
            });
        }

        if (Schema::hasTable('isp_sla_monthly')) {
            Schema::table('isp_sla_monthly', function (Blueprint $table) {
                if (!$this->indexExists('isp_sla_monthly', 'isp_monthly_period_idx'))
                    $table->index(['year', 'month'], 'isp_monthly_period_idx');
                if (!$this->indexExists('isp_sla_monthly', 'isp_monthly_contract_idx'))
                    $table->index('contract_id', 'isp_monthly_contract_idx');
            });
        }

        if (Schema::hasTable('isp_down_history')) {
            Schema::table('isp_down_history', function (Blueprint $table) {
                if (!$this->indexExists('isp_down_history', 'isp_down_date_idx'))
                    $table->index('incident_date', 'isp_down_date_idx');
                if (!$this->indexExists('isp_down_history', 'isp_down_contract_idx'))
                    $table->index('contract_id', 'isp_down_contract_idx');
                if (!$this->indexExists('isp_down_history', 'isp_down_lookup_idx'))
                    $table->index(['contract_id', 'incident_date'], 'isp_down_lookup_idx');
            });
        }
    }

    public function down(): void {}
};
