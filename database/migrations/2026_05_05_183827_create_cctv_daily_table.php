<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cctv_daily', function (Blueprint $table) {
            $table->id();
            $table->string('sensor_id', 20);
            $table->string('location', 100);
            $table->string('provider', 50);
            $table->string('description', 50);
            $table->date('report_date');
            $table->decimal('value_mbps', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['sensor_id', 'description', 'report_date'], 'cctv_daily_unique');
            $table->index(['report_date', 'location', 'provider', 'description'], 'cctv_daily_summary_idx');
            $table->index(['report_date', 'location', 'provider'], 'cctv_daily_data_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cctv_daily');
    }
};
