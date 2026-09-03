<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            if (!Schema::hasColumn('inspections', 'dept_head'))
                $table->string('dept_head')->nullable()->after('department');
            if (!Schema::hasColumn('inspections', 'asset_tag'))
                $table->string('asset_tag')->nullable()->after('device_name');
            if (!Schema::hasColumn('inspections', 'serial_number'))
                $table->string('serial_number')->nullable()->after('asset_tag');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['dept_head', 'asset_tag', 'serial_number']);
        });
    }
};
