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
        $dropIndexIfExists = function (string $table, string $indexName) {
            try {
                $exists = match (\Illuminate\Support\Facades\DB::getDriverName()) {
                    'sqlite' => collect(\Illuminate\Support\Facades\DB::select("PRAGMA index_list('$table')"))
                        ->contains(fn ($index) => ($index->name ?? null) === $indexName),
                    'mysql'  => collect(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `$table`"))
                        ->contains(fn ($index) => ($index->Key_name ?? null) === $indexName),
                    'pgsql'  => collect(\Illuminate\Support\Facades\DB::select(
                        "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                        [$table, $indexName]
                    ))->isNotEmpty(),
                    default  => false,
                };
            } catch (\Throwable) {
                $exists = false;
            }

            if ($exists) {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        };

        $dropIndexIfExists('stbs', 'stbs_deliver_date_index');
        $dropIndexIfExists('stbs', 'stbs_it_checker_id_index');
        $dropIndexIfExists('stbs', 'stbs_it_approved_id_index');
        $dropIndexIfExists('stbs', 'stbs_building_index');
        $dropIndexIfExists('stbs', 'stbs_use_date_index');
        $dropIndexIfExists('stbs', 'stbs_batch_no_index');
        $dropIndexIfExists('stbs', 'stbs_req_doc_no_index');
        $dropIndexIfExists('stbs', 'stbs_po_doc_no_index');
        $dropIndexIfExists('stbs', 'stbs_created_at_index');
        $dropIndexIfExists('stbs', 'stbs_updated_at_index');

        Schema::table('stbs', function (Blueprint $table) {
            $cols = [
                'deliver_date',
                'req_doc_no',
                'building',
                'it_checker_id',
                'it_approved_id',
                'batch_no',
                'it_checker_signature_path',
                'it_checker_signed_at',
                'it_approved_signature_path',
                'it_approved_signed_at',
                'requester_dept_head_signature_path',
                'requester_dept_head_signed_at'
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('stbs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->date('deliver_date')->nullable();
            $table->string('req_doc_no')->nullable();
            $table->string('building')->nullable();
            $table->date('use_date')->nullable();
            $table->unsignedBigInteger('it_checker_id')->nullable();
            $table->unsignedBigInteger('it_approved_id')->nullable();
            $table->string('batch_no')->nullable();
            $table->string('it_checker_signature_path')->nullable();
            $table->timestamp('it_checker_signed_at')->nullable();
            $table->string('it_approved_signature_path')->nullable();
            $table->timestamp('it_approved_signed_at')->nullable();
            $table->string('requester_dept_head_signature_path')->nullable();
            $table->timestamp('requester_dept_head_signed_at')->nullable();
        });
    }
};
