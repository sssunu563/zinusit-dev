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
        Schema::table('stb_items', function (Blueprint $blueprint) {
            $blueprint->string('condition')->nullable()->after('jumlah')->comment('Kondisi barang: Good, Broken, Missing, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stb_items', function (Blueprint $blueprint) {
            $blueprint->dropColumn('condition');
        });
    }
};
