<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── cctv_devices ─────────────────────────────────────────────────────
        if (!Schema::hasTable('cctv_devices')) {
        Schema::create('cctv_devices', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->string('source_instance', 20);
            $table->unsignedBigInteger('source_id');
            $table->string('device_name', 200);
            $table->string('ip_address', 50)->default('-');
            $table->string('host_group', 150)->nullable();
            $table->string('device_type', 20);
            $table->string('location', 100)->nullable();
            $table->string('site', 50)->nullable();
            $table->string('last_status', 10)->default('UNKNOWN');
            $table->timestamp('last_sync')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_excluded')->default(false);
            $table->text('maintenance_note')->nullable();
            $table->timestamp('maintenance_until')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_instance', 'source_id'], 'cd_source_unique');
            $table->index(['device_type', 'site'],  'cd_type_site_idx');
            $table->index('source',                 'cd_source_idx');
            $table->index('source_instance',        'cd_instance_idx');
            $table->index('is_active',              'cd_active_idx');
            $table->index('is_excluded',            'cd_excluded_idx');
            $table->index('location',               'cd_location_idx');
        });
        }

        // ── cctv_uptime_daily ─────────────────────────────────────────────────
        if (!Schema::hasTable('cctv_uptime_daily')) {
        Schema::create('cctv_uptime_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('cctv_devices')->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('uptime_percent', 6, 3)->nullable();
            $table->string('status', 10)->default('UNKNOWN');
            $table->timestamps();

            $table->unique(['device_id', 'report_date'], 'cud_device_date_unique');
            $table->index('report_date',  'cud_date_idx');
            $table->index(['device_id', 'report_date'], 'cud_device_date_idx');
        });
        }

        // ── cctv_fetch_logs ───────────────────────────────────────────────────
        if (!Schema::hasTable('cctv_fetch_logs')) {
        Schema::create('cctv_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->date('fetch_date');
            $table->string('source', 20);
            $table->string('source_instance', 20);
            $table->string('device_type', 20);
            $table->string('group_name', 200)->nullable();
            $table->string('status', 10);
            $table->unsignedSmallInteger('devices_ok')->default(0);
            $table->unsignedSmallInteger('devices_fail')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fetch_date', 'source'],       'cfl_date_source_idx');
            $table->index(['fetch_date', 'device_type'],  'cfl_date_type_idx');
            $table->index('status',                       'cfl_status_idx');
        });
        }

        // ── cctv_maintenance_logs ─────────────────────────────────────────────
        if (!Schema::hasTable('cctv_maintenance_logs')) {
        Schema::create('cctv_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('cctv_devices')->cascadeOnDelete();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->date('started_at');
            $table->date('resolved_at')->nullable();
            $table->string('event_type', 30)->default('maintenance');
            $table->text('notes')->nullable();
            $table->boolean('is_auto')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'status'],  'cml_device_status_idx');
            $table->index(['started_at', 'status'], 'cml_date_status_idx');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cctv_maintenance_logs');
        Schema::dropIfExists('cctv_fetch_logs');
        Schema::dropIfExists('cctv_uptime_daily');
        Schema::dropIfExists('cctv_devices');
    }
};
