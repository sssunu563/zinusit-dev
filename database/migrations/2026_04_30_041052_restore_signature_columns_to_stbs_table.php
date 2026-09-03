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
            if (!Schema::hasColumn('stbs', 'deliver_date')) {
                $table->date('deliver_date')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'req_doc_no')) {
                $table->string('req_doc_no')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'building')) {
                $table->string('building')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'it_checker_id')) {
                $table->unsignedBigInteger('it_checker_id')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'it_approved_id')) {
                $table->unsignedBigInteger('it_approved_id')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'batch_no')) {
                $table->string('batch_no')->nullable();
            }
            
            // Signature paths as longText to accommodate large base64 strings
            if (!Schema::hasColumn('stbs', 'it_checker_signature_path')) {
                $table->longText('it_checker_signature_path')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'it_checker_signed_at')) {
                $table->timestamp('it_checker_signed_at')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'it_approved_signature_path')) {
                $table->longText('it_approved_signature_path')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'it_approved_signed_at')) {
                $table->timestamp('it_approved_signed_at')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'requester_dept_head_signature_path')) {
                $table->longText('requester_dept_head_signature_path')->nullable();
            }
            if (!Schema::hasColumn('stbs', 'requester_dept_head_signed_at')) {
                $table->timestamp('requester_dept_head_signed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->dropColumn([
                'deliver_date',
                'req_doc_no',
                'building',
                'it_checker_id',
                'it_approved_id',
                'batch_no',
                'it_checker_signature_path',
                'it_checker_signed_at',
                'it_approved_signature_path',
                'it_approved_signed_at',
                'requester_dept_head_signature_path',
                'requester_dept_head_signed_at'
            ]);
        });
    }
};
