<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stb_items') || !Schema::hasColumn('stb_items', 'kategori')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            $table->dropIndex(['kategori']);
            $table->dropColumn('kategori');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stb_items') || Schema::hasColumn('stb_items', 'kategori')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('nama')->index();
        });
    }
};
