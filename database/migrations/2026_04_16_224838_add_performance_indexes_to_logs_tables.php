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
        Schema::table('action_logs', function (Blueprint $table) {
            $table->index('action_type', 'idx_action_logs_action_type');
            $table->index('created_at', 'idx_action_logs_created_at');
        });

        Schema::table('auth_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_auth_logs_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropIndex('idx_action_logs_action_type');
            $table->dropIndex('idx_action_logs_created_at');
        });

        Schema::table('auth_logs', function (Blueprint $table) {
            $table->dropIndex('idx_auth_logs_created_at');
        });
    }
};
