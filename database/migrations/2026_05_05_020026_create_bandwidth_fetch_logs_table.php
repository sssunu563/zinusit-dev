<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bandwidth_fetch_logs', function (Blueprint $table) {
            $table->id();
            $table->date('fetch_date');           // tanggal data yang di-fetch
            $table->string('status', 20);         // 'success' | 'partial' | 'failed'
            $table->integer('sensors_ok')->default(0);
            $table->integer('sensors_fail')->default(0);
            $table->text('notes')->nullable();    // error messages / detail
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index('fetch_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bandwidth_fetch_logs');
    }
};
