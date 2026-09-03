<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('user_snipeit_id')->nullable()->after('user');
            $table->unsignedBigInteger('it_staff_id')->nullable()->after('it_staff');
            $table->unsignedBigInteger('checked_by_id')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn(['user_snipeit_id', 'it_staff_id', 'checked_by_id']);
        });
    }
};
