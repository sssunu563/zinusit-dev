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
        Schema::dropIfExists('device_reports');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't recreate the old table in down migration
        // as we're migrating to the new inspections table
    }
};
