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
            if (!Schema::hasColumn('stb_items', 'computer_id')) {
                $table->unsignedBigInteger('computer_id')->nullable()->index();
            }
        });

        $indexExists = function (string $table, string $indexName): bool {
            try {
                return match (DB::getDriverName()) {
                    'sqlite' => collect(DB::select("PRAGMA index_list('$table')"))
                        ->contains(fn ($index) => ($index->name ?? null) === $indexName),
                    'mysql' => collect(DB::select("SHOW INDEX FROM `$table`"))
                        ->contains(fn ($index) => ($index->Key_name ?? null) === $indexName),
                    'pgsql' => collect(DB::select(
                        'SELECT indexname FROM pg_indexes WHERE tablename = ?',
                        [$table],
                    ))->contains(fn ($index) => ($index->indexname ?? null) === $indexName),
                    default => false,
                };
            } catch (\Throwable $e) {
                return false;
            }
        };

        Schema::table('stb_items', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('stb_items', 'stb_items_asset_no_index')) {
                $table->dropIndex('stb_items_asset_no_index');
            }

            if (Schema::hasColumn('stb_items', 'asset_no')) {
                $table->dropColumn('asset_no');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stb_items')) {
            return;
        }

        Schema::table('stb_items', function (Blueprint $table) {
            if (!Schema::hasColumn('stb_items', 'asset_no')) {
                $table->string('asset_no')->nullable()->index();
            }
        });

        Schema::table('stb_items', function (Blueprint $table) {
            if (Schema::hasColumn('stb_items', 'computer_id')) {
                $table->dropColumn('computer_id');
            }
        });
    }
};
