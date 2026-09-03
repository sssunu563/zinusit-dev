<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables if exist
        Schema::dropIfExists('backup_monthly');
        Schema::dropIfExists('uptime_fetch_logs');
        Schema::dropIfExists('uptime_daily');
        Schema::dropIfExists('uptime_devices');

        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);           // 'prtg' | 'zabbix'
            $table->string('source_instance', 20);  // 'main' (prtg) | 'f1' | 'f2' | 'f3' (zabbix)
            $table->unsignedBigInteger('source_id'); // sensor_id (prtg) or host_id (zabbix)
            $table->string('device_name', 200);
            $table->string('ip_address', 50)->default('-');
            $table->string('host_group', 150)->nullable();  // ASLI dari PRTG/Zabbix
            $table->string('probe', 100)->nullable();       // probe PRTG / Zabbix server name
            $table->string('location', 100)->nullable();    // ZGI BGR F1, ZGI KRW F2, ZDI TGR F3
            $table->string('site', 50)->nullable();         // F1 Bogor, F2 Karawang, F3 Tangerang
            $table->string('last_status', 10)->default('UNKNOWN'); // UP | DOWN | UNKNOWN
            $table->timestamp('last_sync')->nullable();
            $table->boolean('monitor_backup')->default(false); // dipantau backup atau tidak
            $table->boolean('is_active')->default(true);
            // Note untuk device yang sengaja dimatikan
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_until')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_instance', 'source_id'], 'nd_source_unique');
            $table->index(['site', 'host_group'],  'nd_site_group_idx');
            $table->index('source',                'nd_source_idx');
            $table->index('source_instance',       'nd_instance_idx');
            $table->index('monitor_backup',        'nd_backup_idx');
            $table->index('is_active',             'nd_active_idx');
        });

        Schema::create('network_uptime_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id'); // FK → network_devices.id
            $table->date('report_date');
            $table->decimal('uptime_percent', 6, 3)->default(0);
            $table->string('status', 10)->default('UP'); // UP | DOWN
            $table->timestamps();

            $table->unique(['device_id', 'report_date'],    'nud_unique');
            $table->index(['report_date', 'device_id'],     'nud_lookup_idx');
            $table->index('report_date',                    'nud_date_idx');

            $table->foreign('device_id')->references('id')->on('network_devices')->cascadeOnDelete();
        });

        Schema::create('network_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->date('fetch_date');
            $table->string('source', 20);           // 'prtg' | 'zabbix'
            $table->string('source_instance', 20);  // 'main' | 'f1' | 'f2' | 'f3'
            $table->string('group_name', 150)->nullable();
            $table->string('status', 20);           // success | partial | failed
            $table->integer('devices_ok')->default(0);
            $table->integer('devices_fail')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index(['fetch_date', 'source', 'source_instance'], 'nfl_lookup_idx');
            $table->index('fetch_date',                                 'nfl_date_idx');
        });

        Schema::create('network_backup_monthly', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id'); // FK → network_devices.id
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');    // 1-12
            $table->boolean('has_backup')->default(false); // B atau kosong
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'year', 'month'], 'nbm_unique');
            $table->index(['year', 'month'],               'nbm_period_idx');
            $table->index('device_id',                     'nbm_device_idx');

            $table->foreign('device_id')->references('id')->on('network_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_backup_monthly');
        Schema::dropIfExists('network_fetch_logs');
        Schema::dropIfExists('network_uptime_daily');
        Schema::dropIfExists('network_devices');
    }
};
