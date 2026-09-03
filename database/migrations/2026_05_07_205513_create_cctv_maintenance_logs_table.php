<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate cctv_fetch_logs with full schema (old table was empty/incomplete)
        Schema::dropIfExists('cctv_fetch_logs');
        Schema::create('cctv_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->date('fetch_date');
            $table->string('source', 20);
            $table->string('source_instance', 20);
            $table->string('device_type', 20)->default('cctv');
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

        // Add missing columns to cctv_devices if not present
        Schema::table('cctv_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('cctv_devices', 'is_excluded')) {
                $table->boolean('is_excluded')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('cctv_devices', 'maintenance_note')) {
                $table->text('maintenance_note')->nullable();
            }
            if (!Schema::hasColumn('cctv_devices', 'maintenance_until')) {
                $table->timestamp('maintenance_until')->nullable();
            }
            if (!Schema::hasColumn('cctv_devices', 'device_type')) {
                $table->string('device_type', 20)->default('cctv');
            }
        });

        // Create cctv_maintenance_logs
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
    }
};
