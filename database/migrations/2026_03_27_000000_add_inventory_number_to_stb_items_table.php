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
        if (!Schema::hasTable('stb_items') || Schema::hasColumn('stb_items', 'inventory_number')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            $table->string('inventory_number')->nullable()->after('serial_no')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('stb_items') || !Schema::hasColumn('stb_items', 'inventory_number')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            $table->dropIndex(['inventory_number']);
            $table->dropColumn('inventory_number');
        });
    }
};
