<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('maintenance_type', 32)
                ->default('Maintenance')
                ->after('asset_reference_snapshot')
                ->index();
        });

        DB::table('tickets')
            ->whereNull('maintenance_type')
            ->update(['maintenance_type' => 'Maintenance']);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('maintenance_type');
        });
    }
};