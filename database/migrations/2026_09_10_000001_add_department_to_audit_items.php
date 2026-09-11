<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_items', function (Blueprint $table): void {
            $table->string('expected_department', 120)->nullable()->after('expected_location');
        });
    }

    public function down(): void
    {
        Schema::table('audit_items', function (Blueprint $table): void {
            $table->dropColumn('expected_department');
        });
    }
};