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
        Schema::table('stbs', function (Blueprint $table) {
            $table->longText('it_drafter_signature_path')->nullable()->change();
            $table->longText('it_checker_signature_path')->nullable()->change();
            $table->longText('it_approved_signature_path')->nullable()->change();
            $table->longText('requester_received_signature_path')->nullable()->change();
            $table->longText('requester_dept_head_signature_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->string('it_drafter_signature_path')->nullable()->change();
            $table->string('it_checker_signature_path')->nullable()->change();
            $table->string('it_approved_signature_path')->nullable()->change();
            $table->string('requester_received_signature_path')->nullable()->change();
            $table->string('requester_dept_head_signature_path')->nullable()->change();
        });
    }
};
