<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('snipeit_asset_id')->nullable()->after('department')->index();
            $table->string('asset_reference_snapshot', 120)->nullable()->after('snipeit_asset_id')->index();
            $table->unsignedBigInteger('snipeit_maintenance_id')->nullable()->after('date_closed')->index();
            $table->string('snipeit_sync_status', 32)->nullable()->after('snipeit_maintenance_id')->index();
            $table->text('snipeit_sync_message')->nullable()->after('snipeit_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'snipeit_asset_id',
                'asset_reference_snapshot',
                'snipeit_maintenance_id',
                'snipeit_sync_status',
                'snipeit_sync_message',
            ]);
        });
    }
};