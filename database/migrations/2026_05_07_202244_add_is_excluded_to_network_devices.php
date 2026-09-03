<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            // Device yang di-exclude oleh admin: tidak masuk hitungan avg, tidak di-export,
            // tidak kena auto-ticket — tapi data fetch tetap disimpan (nilai 0 jika down)
            $table->boolean('is_excluded')->default(false)->after('is_active')->index('nd_excluded_idx');
        });
    }

    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropIndex('nd_excluded_idx');
            $table->dropColumn('is_excluded');
        });
    }
};
