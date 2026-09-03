<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->ensureIndex('stbs', 'user_id');
        $this->ensureIndex('stbs', 'status');
        $this->ensureIndex('stbs', 'created_at');

        $this->ensureIndex('stb_items', 'stb_id');
        $this->ensureIndex('stb_items', 'snipeit_id');

        $this->ensureIndex('tickets', 'user_id');
        $this->ensureIndex('tickets', 'status');
        $this->ensureIndex('tickets', 'created_at');

        $this->ensureIndex('inspections', 'user_id');
        $this->ensureIndex('inspections', 'status');
        $this->ensureIndex('inspections', 'created_at');

        $this->ensureIndex('peminjamans', 'user_id');
        $this->ensureIndex('peminjamans', 'status');
        $this->ensureIndex('peminjamans', 'created_at');

        $this->ensureIndex('users', 'employee_num');
        $this->ensureIndex('users', 'email');
    }

    private function ensureIndex($table, $column)
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            $indexes = Schema::getIndexes($table);
            
            $hasIndex = false;
            foreach ($indexes as $index) {
                if (in_array($column, $index['columns'])) {
                    $hasIndex = true;
                    break;
                }
            }

            if (!$hasIndex) {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($column) {
                    $tableBlueprint->index($column);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration needed for just ensuring indexes exist
    }
};
