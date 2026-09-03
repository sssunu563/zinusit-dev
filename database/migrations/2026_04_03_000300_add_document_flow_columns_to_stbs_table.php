<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            if (!Schema::hasColumn('stbs', 'document_type')) {
                $table->string('document_type', 32)->nullable()->after('status');
                $table->index('document_type');
            }

            if (!Schema::hasColumn('stbs', 'movement_type')) {
                $table->string('movement_type', 32)->nullable()->after('document_type');
                $table->index('movement_type');
            }

            if (!Schema::hasColumn('stbs', 'linked_stb_id')) {
                $table->unsignedBigInteger('linked_stb_id')->nullable()->after('movement_type');
                $table->index('linked_stb_id');
            }

            if (!Schema::hasColumn('stbs', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('linked_stb_id');
                $table->index('returned_at');
            }
        });

        DB::table('stbs')
            ->whereNull('document_type')
            ->update([
                'document_type' => DB::raw("CASE status WHEN 3 THEN 'loan' WHEN 4 THEN 'service' ELSE 'handover' END"),
            ]);

        DB::table('stbs')
            ->whereNull('movement_type')
            ->update([
                'movement_type' => DB::raw("CASE status WHEN 2 THEN 'return' ELSE 'out' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            if (Schema::hasColumn('stbs', 'returned_at')) {
                $table->dropIndex(['returned_at']);
                $table->dropColumn('returned_at');
            }

            if (Schema::hasColumn('stbs', 'linked_stb_id')) {
                $table->dropIndex(['linked_stb_id']);
                $table->dropColumn('linked_stb_id');
            }

            if (Schema::hasColumn('stbs', 'movement_type')) {
                $table->dropIndex(['movement_type']);
                $table->dropColumn('movement_type');
            }

            if (Schema::hasColumn('stbs', 'document_type')) {
                $table->dropIndex(['document_type']);
                $table->dropColumn('document_type');
            }
        });
    }
};