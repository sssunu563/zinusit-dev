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
            $table->unsignedBigInteger('snipeit_id')->nullable()->after('target_id');
            $table->string('snipeit_type')->nullable()->after('snipeit_id');
            
            $table->index(['snipeit_id', 'snipeit_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropIndex(['snipeit_id', 'snipeit_type']);
            $table->dropColumn(['snipeit_id', 'snipeit_type']);
        });
    }
};
