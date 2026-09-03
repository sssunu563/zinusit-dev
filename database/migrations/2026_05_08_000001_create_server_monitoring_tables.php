<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables if exist
        Schema::dropIfExists('server_temperature_daily');
        Schema::dropIfExists('server_resource_daily');
        Schema::dropIfExists('server_fetch_logs');
        Schema::dropIfExists('server_devices');

        Schema::create('server_devices', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('prtg');     // 'prtg' | 'zabbix'
            $table->string('source_instance', 20)->default('main'); // 'main' (prtg) | 'f1' | 'f2' | 'f3' (zabbix)
            $table->unsignedBigInteger('source_id');           // sensor_id (prtg) or host_id (zabbix)
            $table->string('device_name', 200);
            $table->string('ip_address', 50)->default('-');
            $table->string('host_group', 150)->nullable();     // ASLI dari PRTG/Zabbix
            $table->string('probe', 100)->nullable();          // probe PRTG / Zabbix server name
            $table->string('location', 100)->nullable();       // ZGI BGR F1, ZGI KRW F2, ZDI TGR F3
            $table->string('site', 50)->nullable();            // F1 Bogor, F2 Karawang, F3 Tangerang
            $table->string('last_status', 10)->default('UNKNOWN'); // UP | DOWN | UNKNOWN
            $table->timestamp('last_sync')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_excluded')->default(false);
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_until')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_instance', 'source_id'], 'sd_source_unique');
            $table->index(['site', 'host_group'],  'sd_site_group_idx');
            $table->index('source',                'sd_source_idx');
            $table->index('source_instance',       'sd_instance_idx');
            $table->index('is_active',             'sd_active_idx');
            $table->index('is_excluded',           'sd_excluded_idx');
        });

        Schema::create('server_resource_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('host_id');          // FK → server_devices.source_id (for Python script compatibility)
            $table->date('report_date');
            $table->decimal('cpu_usage_percent', 6, 2)->nullable();
            $table->decimal('memory_usage_percent', 6, 2)->nullable();
            $table->string('hdd_free_percent', 500)->nullable(); // JSON or pipe-separated "Disk1: 45% | Disk2: 67%"
            $table->timestamps();

            $table->unique(['host_id', 'report_date'],    'srd_unique');
            $table->index(['report_date', 'host_id'],     'srd_lookup_idx');
            $table->index('report_date',                  'srd_date_idx');
            $table->index('host_id',                      'srd_host_idx');
        });

        Schema::create('server_temperature_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sensor_id');       // sensor_id from PRTG
            $table->string('location', 100);               // F1 Bogor, F2 Karawang, F3 Tangerang
            $table->string('description', 255)->nullable(); // F1 Server Room Temp
            $table->date('report_date');
            $table->decimal('value_celsius', 6, 2);
            $table->timestamps();

            $table->unique(['sensor_id', 'report_date'],   'std_unique');
            $table->index(['report_date', 'sensor_id'],    'std_lookup_idx');
            $table->index('report_date',                   'std_date_idx');
            $table->index('location',                      'std_location_idx');
        });

        Schema::create('server_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->date('fetch_date');
            $table->string('group_name', 150)->nullable();  // 'resource' | 'temperature'
            $table->string('status', 20);                   // 'success' | 'partial' | 'failed'
            $table->integer('devices_ok')->default(0);
            $table->integer('devices_fail')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index(['fetch_date', 'group_name'],    'sfl_lookup_idx');
            $table->index('fetch_date',                    'sfl_date_idx');
            $table->index('triggered_by',                  'sfl_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_fetch_logs');
        Schema::dropIfExists('server_temperature_daily');
        Schema::dropIfExists('server_resource_daily');
        Schema::dropIfExists('server_devices');
    }
};
