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
        Schema::dropIfExists('backup_monthly');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // If needed to rollback, create the table structure here
        // For now, we're committing to dropping this unused table
        Schema::create('backup_monthly', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
