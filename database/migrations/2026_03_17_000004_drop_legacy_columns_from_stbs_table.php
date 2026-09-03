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

        $legacyColumns = [
            'stb_id',
            'it_drafter',
            'it_checker',
            'it_approved',
            'company',
            'department',
            'location',
            'asset_no_all',
        ];

        $getIndexNames = function (string $table): array {
            try {
                return match (DB::getDriverName()) {
                    'sqlite' => collect(DB::select("PRAGMA index_list('$table')"))
                        ->pluck('name')
                        ->filter()
                        ->values()
                        ->all(),
                    'mysql' => collect(DB::select("SHOW INDEX FROM `$table`"))
                        ->pluck('Key_name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'pgsql' => collect(DB::select(
                        'SELECT indexname FROM pg_indexes WHERE tablename = ?',
                        [$table],
                    ))->pluck('indexname')
                        ->filter()
                        ->values()
                        ->all(),
                    default => [],
                };
            } catch (\Throwable $e) {
                return [];
            }
        };

        Schema::table('stbs', function (Blueprint $table) use ($legacyColumns, $getIndexNames) {
            $indexNames = $getIndexNames('stbs');

            foreach ($legacyColumns as $column) {
                foreach ($indexNames as $indexName) {
                    if (str_contains($indexName, $column)) {
                        $table->dropIndex($indexName);
                    }
                }
            }
        });

        Schema::table('stbs', function (Blueprint $table) use ($legacyColumns) {
            $columnsToDrop = array_values(array_filter(
                $legacyColumns,
                fn (string $column) => Schema::hasColumn('stbs', $column),
            ));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
    }
};
