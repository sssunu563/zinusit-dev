<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uptime_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('host_id')->unique(); // PRTG sensor ID
            $table->string('device_name', 200);
            $table->string('ip_address', 50)->default('-');
            $table->string('host_group', 100)->nullable();  // PRTG group name
            $table->string('site', 50)->nullable();         // F1 Bogor, F2 Karawang, F3 Tangerang
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('site',       'uptime_devices_site_idx');
            $table->index('host_group', 'uptime_devices_group_idx');
            $table->index('is_active',  'uptime_devices_active_idx');
        });

        Schema::create('uptime_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('host_id');          // PRTG sensor ID
            $table->date('report_date');
            $table->decimal('uptime_percent', 6, 3)->default(0); // 99.653
            $table->string('status', 10)->default('UP');    // UP | DOWN
            $table->timestamps();

            $table->unique(['host_id', 'report_date'],      'uptime_daily_unique');
            $table->index(['report_date', 'host_id'],       'uptime_daily_lookup_idx');
            $table->index('report_date',                    'uptime_daily_date_idx');
            $table->index('host_id',                        'uptime_daily_host_idx');

            $table->foreign('host_id')->references('host_id')->on('uptime_devices')->cascadeOnDelete();
        });

        Schema::create('uptime_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->date('fetch_date');
            $table->string('site', 50)->nullable();         // null = all sites
            $table->string('status', 20);                   // success | partial | failed
            $table->integer('devices_ok')->default(0);
            $table->integer('devices_fail')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index(['fetch_date', 'site'],           'uptime_log_lookup_idx');
        });

        Schema::create('backup_monthly', function (Blueprint $table) {
            $table->id();
            $table->string('site', 50);                     // F1 Bogor, F2 Karawang, F3 Tangerang
            $table->string('backup_type', 100);             // Config Backup, Data Backup, etc.
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');           // 1-12
            $table->boolean('has_backup')->default(false);  // true = B, false = kosong
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site', 'backup_type', 'year', 'month'], 'backup_monthly_unique');
            $table->index(['year', 'month'],                'backup_monthly_period_idx');
            $table->index('site',                           'backup_monthly_site_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_monthly');
        Schema::dropIfExists('uptime_fetch_logs');
        Schema::dropIfExists('uptime_daily');
        Schema::dropIfExists('uptime_devices');
    }
};
