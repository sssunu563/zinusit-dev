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
            if (!Schema::hasColumn('stbs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->index();
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

        Schema::table('stbs', function (Blueprint $table) use ($indexExists) {
            if ($indexExists('stbs', 'stbs_name_index')) {
                $table->dropIndex('stbs_name_index');
            }

            if (Schema::hasColumn('stbs', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stbs')) {
            return;
        }

        Schema::table('stbs', function (Blueprint $table) {
            if (!Schema::hasColumn('stbs', 'name')) {
                $table->string('name')->nullable()->index();
            }
        });

        Schema::table('stbs', function (Blueprint $table) {
            if (Schema::hasColumn('stbs', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};
