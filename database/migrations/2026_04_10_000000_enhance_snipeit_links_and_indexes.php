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
        // 1. Table: inspections (Add Snipe-IT mapping)
        Schema::table('inspections', function (Blueprint $table) {
            if (!Schema::hasColumn('inspections', 'snipeit_asset_id')) {
                $table->integer('snipeit_asset_id')->nullable()->after('photo');
            }
            if (!Schema::hasColumn('inspections', 'asset_reference_snapshot')) {
                $table->string('asset_reference_snapshot')->nullable()->after('snipeit_asset_id');
            }
            
            // Add Indexes
            $table->index('snipeit_asset_id');
            $table->index('asset_reference_snapshot');
        });

        // 2. Table: stbs (Add missing indexes for foreign keys)
        Schema::table('stbs', function (Blueprint $table) {
            $table->index('it_drafter_id');
            $table->index('it_checker_id');
            $table->index('it_approved_id');
        });

        // 3. Table: tickets (Add missing index for foreign key)
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('created_by');
        });

        // 4. Table: asset_stock_histories (Add missing index for foreign key)
        Schema::table('asset_stock_histories', function (Blueprint $table) {
            $table->index('created_by');
        });

        // 5. Table: auth_logs (Add missing index for foreign key)
        Schema::table('auth_logs', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropIndex(['snipeit_asset_id']);
            $table->dropIndex(['asset_reference_snapshot']);
            $table->dropColumn(['snipeit_asset_id', 'asset_reference_snapshot']);
        });

        Schema::table('stbs', function (Blueprint $table) {
            $table->dropIndex(['it_drafter_id']);
            $table->dropIndex(['it_checker_id']);
            $table->dropIndex(['it_approved_id']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('asset_stock_histories', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('auth_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
