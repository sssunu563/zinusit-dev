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
            $table->string('it_drafter_signature_path')->nullable()->after('remark');
            $table->timestamp('it_drafter_signed_at')->nullable()->after('it_drafter_signature_path');
            $table->string('it_checker_signature_path')->nullable()->after('it_drafter_signed_at');
            $table->timestamp('it_checker_signed_at')->nullable()->after('it_checker_signature_path');
            $table->string('it_approved_signature_path')->nullable()->after('it_checker_signed_at');
            $table->timestamp('it_approved_signed_at')->nullable()->after('it_approved_signature_path');
            $table->string('requester_received_signature_path')->nullable()->after('it_approved_signed_at');
            $table->timestamp('requester_received_signed_at')->nullable()->after('requester_received_signature_path');
            $table->string('requester_dept_head_signature_path')->nullable()->after('requester_received_signed_at');
            $table->timestamp('requester_dept_head_signed_at')->nullable()->after('requester_dept_head_signature_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->dropColumn([
                'it_drafter_signature_path',
                'it_drafter_signed_at',
                'it_checker_signature_path',
                'it_checker_signed_at',
                'it_approved_signature_path',
                'it_approved_signed_at',
                'requester_received_signature_path',
                'requester_received_signed_at',
                'requester_dept_head_signature_path',
                'requester_dept_head_signed_at',
            ]);
        });
    }
};
