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
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('location')->index();
            $table->string('user')->index();
            $table->string('company')->index();
            $table->string('department')->index();
            $table->string('report_id')->unique();
            $table->datetime('change_time')->nullable()->index();
            $table->string('report_type')->index();
            $table->date('date')->index();
            $table->string('device_category')->index();
            $table->string('device_name');
            $table->string('checked_by')->index();
            $table->date('checked_date')->index();
            $table->text('issue_description');
            $table->text('solution');
            $table->text('remarks')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();

            // Additional composite indexes for common queries
            $table->index(['device_category', 'date']);
            $table->index(['company', 'department']);
            $table->index(['checked_by', 'checked_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
