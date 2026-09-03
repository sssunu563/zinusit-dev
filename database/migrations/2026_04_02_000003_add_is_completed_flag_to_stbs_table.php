<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stbs')) {
            return;
        }

        Schema::table('stbs', function (Blueprint $table) {
            if (!Schema::hasColumn('stbs', 'is_completed')) {
                $table->boolean('is_completed')->default(false)->after('completed_at');
            }
        });

        DB::table('stbs')->update([
            'is_completed' => DB::raw("CASE WHEN completed_at IS NOT NULL AND completed_pdf_path IS NOT NULL THEN 1 ELSE 0 END"),
        ]);

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
            } catch (\Throwable) {
                return false;
            }
        };

        Schema::table('stbs', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('stbs', 'stbs_is_completed_created_at_index')) {
                $table->index(['is_completed', 'created_at'], 'stbs_is_completed_created_at_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stbs')) {
            return;
        }

        try {
            Schema::table('stbs', function (Blueprint $table) {
                $table->dropIndex('stbs_is_completed_created_at_index');
            });
        } catch (\Throwable) {
        }

        Schema::table('stbs', function (Blueprint $table) {
            if (Schema::hasColumn('stbs', 'is_completed')) {
                $table->dropColumn('is_completed');
            }
        });
    }
};