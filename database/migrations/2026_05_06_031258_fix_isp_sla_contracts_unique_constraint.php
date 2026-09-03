<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}`"))
                ->contains(fn ($i) => ($i->Key_name ?? null) === $index);
        } catch (\Throwable) {
            return false;
        }
    }

    public function up(): void
    {
        if (!Schema::hasTable('isp_sla_contracts')) {
            return;
        }

        Schema::table('isp_sla_contracts', function (Blueprint $table) {
            if ($this->indexExists('isp_sla_contracts', 'isp_contracts_unique'))
                $table->dropUnique('isp_contracts_unique');

            if (!$this->indexExists('isp_sla_contracts', 'isp_contracts_unique_v2'))
                $table->unique(['location', 'fct', 'provider'], 'isp_contracts_unique_v2');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('isp_sla_contracts')) {
            return;
        }

        Schema::table('isp_sla_contracts', function (Blueprint $table) {
            if ($this->indexExists('isp_sla_contracts', 'isp_contracts_unique_v2'))
                $table->dropUnique('isp_contracts_unique_v2');

            if (!$this->indexExists('isp_sla_contracts', 'isp_contracts_unique'))
                $table->unique(['location', 'provider'], 'isp_contracts_unique');
        });
    }
};
