<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration adds indexes to existing tables without dropping data
     */
    public function up(): void
    {
        $indexExists = function ($table, $column) {
            try {
                $driver = DB::getDriverName();

                if ($driver === 'sqlite') {
                    $indexes = DB::select("PRAGMA index_list($table)");
                    foreach ($indexes as $index) {
                        if (str_contains($index->name, $column)) {
                            return true;
                        }
                    }
                }

                if ($driver === 'mysql') {
                    $indexes = DB::select("SHOW INDEX FROM `$table`");
                    foreach ($indexes as $index) {
                        if (
                            (isset($index->Column_name) && $index->Column_name === $column)
                            || (isset($index->Key_name) && str_contains($index->Key_name, $column))
                        ) {
                            return true;
                        }
                    }
                }

                if ($driver === 'pgsql') {
                    $indexes = DB::select(
                        'SELECT indexname FROM pg_indexes WHERE tablename = ?',
                        [$table],
                    );

                    foreach ($indexes as $index) {
                        if (isset($index->indexname) && str_contains($index->indexname, $column)) {
                            return true;
                        }
                    }
                }
            } catch (\Exception $e) {
                // If query fails, assume it doesn't exist
            }
            return false;
        };

        if (Schema::hasTable('stbs')) {
            Schema::table('stbs', function (Blueprint $table) use ($indexExists) {
                if (Schema::hasColumn('stbs', 'deliver_date') && !$indexExists('stbs', 'deliver_date')) {
                    $table->index('deliver_date');
                }
                if (Schema::hasColumn('stbs', 'status') && !$indexExists('stbs', 'status')) {
                    $table->index('status');
                }
                if (Schema::hasColumn('stbs', 'it_drafter_id') && !$indexExists('stbs', 'it_drafter_id')) {
                    $table->index('it_drafter_id');
                }
                if (Schema::hasColumn('stbs', 'it_checker_id') && !$indexExists('stbs', 'it_checker_id')) {
                    $table->index('it_checker_id');
                }
                if (Schema::hasColumn('stbs', 'it_approved_id') && !$indexExists('stbs', 'it_approved_id')) {
                    $table->index('it_approved_id');
                }
                if (Schema::hasColumn('stbs', 'req_doc_no') && !$indexExists('stbs', 'req_doc_no')) {
                    $table->index('req_doc_no');
                }
                if (Schema::hasColumn('stbs', 'po_doc_no') && !$indexExists('stbs', 'po_doc_no')) {
                    $table->index('po_doc_no');
                }
                if (Schema::hasColumn('stbs', 'user_id') && !$indexExists('stbs', 'user_id')) {
                    $table->index('user_id');
                }
                if (Schema::hasColumn('stbs', 'group_id') && !$indexExists('stbs', 'group_id')) {
                    $table->index('group_id');
                }
                if (Schema::hasColumn('stbs', 'building') && !$indexExists('stbs', 'building')) {
                    $table->index('building');
                }
                if (Schema::hasColumn('stbs', 'use_date') && !$indexExists('stbs', 'use_date')) {
                    $table->index('use_date');
                }
                if (Schema::hasColumn('stbs', 'batch_no') && !$indexExists('stbs', 'batch_no')) {
                    $table->index('batch_no');
                }
                if (Schema::hasColumn('stbs', 'created_at') && !$indexExists('stbs', 'created_at')) {
                    $table->index('created_at');
                }
                if (Schema::hasColumn('stbs', 'updated_at') && !$indexExists('stbs', 'updated_at')) {
                    $table->index('updated_at');
                }
            });
        }

        if (Schema::hasTable('stb_items')) {
            Schema::table('stb_items', function (Blueprint $table) use ($indexExists) {
                if (Schema::hasColumn('stb_items', 'stb_id') && !$indexExists('stb_items', 'stb_id')) {
                    $table->index('stb_id');
                }
                if (Schema::hasColumn('stb_items', 'nama') && !$indexExists('stb_items', 'nama')) {
                    $table->index('nama');
                }
                if (Schema::hasColumn('stb_items', 'type') && !$indexExists('stb_items', 'type')) {
                    $table->index('type');
                }
                if (Schema::hasColumn('stb_items', 'jumlah') && !$indexExists('stb_items', 'jumlah')) {
                    $table->index('jumlah');
                }
                if (Schema::hasColumn('stb_items', 'computer_id') && !$indexExists('stb_items', 'computer_id')) {
                    $table->index('computer_id');
                }
                if (Schema::hasColumn('stb_items', 'serial_no') && !$indexExists('stb_items', 'serial_no')) {
                    $table->index('serial_no');
                }
                if (Schema::hasColumn('stb_items', 'created_at') && !$indexExists('stb_items', 'created_at')) {
                    $table->index('created_at');
                }
                if (Schema::hasColumn('stb_items', 'updated_at') && !$indexExists('stb_items', 'updated_at')) {
                    $table->index('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
