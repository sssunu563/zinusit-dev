<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('isp_sla_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('location');
            $table->string('fct');
            $table->string('provider');
            $table->string('bandwidth')->nullable();
            $table->decimal('target_pct', 5, 2)->default(99.00);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('isp_sla_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('isp_sla_contracts')->onDelete('cascade');
            $table->integer('year');
            $table->integer('month');
            $table->decimal('uptime_pct', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('isp_down_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('isp_sla_contracts')->onDelete('cascade');
            $table->date('incident_date');
            $table->text('case_description')->nullable();
            $table->text('action_taken')->nullable();
            $table->integer('duration_minutes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_down_history');
        Schema::dropIfExists('isp_sla_monthly');
        Schema::dropIfExists('isp_sla_contracts');
    }
};
