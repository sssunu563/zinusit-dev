<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cctv_nvr_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('cctv_devices')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('check_date');           // tanggal pengecekan
            $table->date('last_record_date');     // tanggal last record di NVR
            // duration_days = check_date - last_record_date (computed, stored for query)
            $table->unsignedSmallInteger('duration_days');
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['device_id', 'year', 'month'], 'cnr_device_month_unique');
            $table->index(['year', 'month'], 'cnr_year_month_idx');
            $table->index('device_id',       'cnr_device_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cctv_nvr_records');
    }
};
