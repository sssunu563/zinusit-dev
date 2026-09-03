<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        if (Schema::hasTable('auth_logs')) {
            Schema::table('auth_logs', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('auth_logs', 'auth_logs_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }

        if (Schema::hasTable('inspections')) {
            Schema::table('inspections', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('inspections', 'inspections_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }

        if (Schema::hasTable('asset_stock_histories')) {
            Schema::table('asset_stock_histories', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('asset_stock_histories', 'asset_stock_histories_lookup_purchase_index')) {
                    $table->index(
                        ['asset_type', 'asset_id', 'purchase_date', 'id'],
                        'asset_stock_histories_lookup_purchase_index',
                    );
                }
            });
        }

        if (Schema::hasTable('stbs')) {
            Schema::table('stbs', function (Blueprint $table) use ($indexExists) {
                if (!$indexExists('stbs', 'stbs_completed_pdf_path_index')) {
                    $table->index('completed_pdf_path');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) use ($indexExists) {
                if (Schema::hasColumn('users', 'snipeit_username') && !$indexExists('users', 'users_snipeit_username_index')) {
                    $table->index('snipeit_username');
                }
            });
        }
    }

    public function down(): void
    {
        $dropIndexIfExists = function (string $table, string $indexName): void {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                    $blueprint->dropIndex($indexName);
                });
            } catch (\Throwable) {
            }
        };

        if (Schema::hasTable('users')) {
            $dropIndexIfExists('users', 'users_snipeit_username_index');
        }

        if (Schema::hasTable('stbs')) {
            $dropIndexIfExists('stbs', 'stbs_completed_pdf_path_index');
        }

        if (Schema::hasTable('asset_stock_histories')) {
            $dropIndexIfExists('asset_stock_histories', 'asset_stock_histories_lookup_purchase_index');
        }

        if (Schema::hasTable('inspections')) {
            $dropIndexIfExists('inspections', 'inspections_created_at_index');
        }

        if (Schema::hasTable('auth_logs')) {
            $dropIndexIfExists('auth_logs', 'auth_logs_created_at_index');
        }
    }
};