<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_items', function (Blueprint $table): void {
            $table->dropForeign(['verified_by']);
            $table->foreignId('verified_by')->nullable()->change();
            $table->foreign('verified_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_items', function (Blueprint $table): void {
            $table->dropForeign(['verified_by']);
            $table->foreignId('verified_by')->nullable(false)->change();
            $table->foreign('verified_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
