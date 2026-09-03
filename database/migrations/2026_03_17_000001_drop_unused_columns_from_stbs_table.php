<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('stbs')) {
            return;
        }

        Schema::table('stbs', function (Blueprint $table) {
            $columnsToDrop = [];

            foreach (['requester_dept_head', 'position', 'sequence_number'] as $column) {
                if (Schema::hasColumn('stbs', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('stbs')) {
            return;
        }

        Schema::table('stbs', function (Blueprint $table) {
            if (!Schema::hasColumn('stbs', 'requester_dept_head')) {
                $table->string('requester_dept_head')->nullable()->index();
            }

            if (!Schema::hasColumn('stbs', 'position')) {
                $table->string('position')->nullable()->index();
            }

            if (!Schema::hasColumn('stbs', 'sequence_number')) {
                $table->unsignedInteger('sequence_number')->default(1)->index();
            }
        });
    }
};
