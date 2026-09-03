<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('updated_at');
            $table->timestamp('snipeit_synced_at')->nullable()->after('completed_at');
            $table->string('snipeit_sync_status')->nullable()->after('snipeit_synced_at'); // 'success', 'failed', 'skipped'
            $table->text('snipeit_sync_log')->nullable()->after('snipeit_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['completed_at', 'snipeit_synced_at', 'snipeit_sync_status', 'snipeit_sync_log']);
        });
    }
};
