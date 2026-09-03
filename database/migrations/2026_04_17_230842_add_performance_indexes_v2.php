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
        Schema::table('inspections', function (Blueprint $table) {
            $table->index('device_name');
            $table->index('email');
            $table->index('leader');
            $table->index('approve_by');
            $table->index('signature_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('is_admin');
            $table->index('employee_num');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropIndex(['device_name']);
            $table->dropIndex(['email']);
            $table->dropIndex(['leader']);
            $table->dropIndex(['approve_by']);
            $table->dropIndex(['signature_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_admin']);
            $table->dropIndex(['employee_num']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
