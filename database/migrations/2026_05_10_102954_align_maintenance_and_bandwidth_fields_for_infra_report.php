<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Upgrade maintenance logs to DateTime for precision
        Schema::table('network_maintenance_logs', function (Blueprint $table) {
            $table->dateTime('started_at')->change();
            $table->dateTime('resolved_at')->nullable()->change();
        });

        Schema::table('cctv_maintenance_logs', function (Blueprint $table) {
            $table->dateTime('started_at')->change();
            $table->dateTime('resolved_at')->nullable()->change();
        });

        Schema::table('server_maintenance_logs', function (Blueprint $table) {
            $table->dateTime('started_at')->change();
            $table->dateTime('resolved_at')->nullable()->change();
        });

        // 2. Add remark to bandwidth for manual input
        Schema::table('bandwidth_daily', function (Blueprint $table) {
            if (!Schema::hasColumn('bandwidth_daily', 'remark')) {
                $table->string('remark')->nullable()->after('value_mbps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('network_maintenance_logs', function (Blueprint $table) {
            $table->date('started_at')->change();
            $table->date('resolved_at')->nullable()->change();
        });

        Schema::table('cctv_maintenance_logs', function (Blueprint $table) {
            $table->date('started_at')->change();
            $table->date('resolved_at')->nullable()->change();
        });

        Schema::table('server_maintenance_logs', function (Blueprint $table) {
            $table->date('started_at')->change();
            $table->date('resolved_at')->nullable()->change();
        });

        Schema::table('bandwidth_daily', function (Blueprint $table) {
            $table->dropColumn('remark');
        });
    }
};
