<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stb_items')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stb_items', 'snipeit_asset_id')) {
                $table->unsignedBigInteger('snipeit_asset_id')->nullable()->after('computer_id')->index();
            }

            if (!Schema::hasColumn('stb_items', 'asset_reference_snapshot')) {
                $table->string('asset_reference_snapshot')->nullable()->after('inventory_number')->index();
            }
        });

        DB::table('stb_items')
            ->whereNull('snipeit_asset_id')
            ->update([
                'snipeit_asset_id' => DB::raw('computer_id'),
            ]);

        DB::table('stb_items')
            ->whereNull('asset_reference_snapshot')
            ->update([
                'asset_reference_snapshot' => DB::raw('inventory_number'),
            ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('stb_items')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            if (Schema::hasColumn('stb_items', 'asset_reference_snapshot')) {
                $table->dropIndex(['asset_reference_snapshot']);
                $table->dropColumn('asset_reference_snapshot');
            }

            if (Schema::hasColumn('stb_items', 'snipeit_asset_id')) {
                $table->dropIndex(['snipeit_asset_id']);
                $table->dropColumn('snipeit_asset_id');
            }
        });
    }
};