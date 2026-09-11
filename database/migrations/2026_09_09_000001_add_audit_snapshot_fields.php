<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_items', function (Blueprint $table): void {
            $table->string('asset_name', 180)->nullable()->after('serial');
            $table->boolean('is_synced')->default(false)->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_items', function (Blueprint $table): void {
            $table->dropColumn(['asset_name', 'is_synced']);
        });
    }
};