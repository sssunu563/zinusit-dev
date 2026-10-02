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
        Schema::create('cctv_regular_reports', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no')->unique();
            $table->string('location')->default('ZGI BGR F1');
            $table->string('checked_by');
            $table->date('checked_date');
            $table->unsignedSmallInteger('week_number');
            $table->unsignedSmallInteger('year');

            // Sections JSON data
            $table->json('loading_area_data')->nullable();
            $table->json('beacukai_data')->nullable();
            $table->json('maintenance_data')->nullable();

            // Signatures
            $table->string('signature_dept1_name')->default('IT');
            $table->string('signature_dept1_signer')->nullable();
            $table->longText('signature_dept1_image')->nullable();
            $table->timestamp('signature_dept1_at')->nullable();

            $table->string('signature_dept2_name')->default('Exim');
            $table->string('signature_dept2_signer')->nullable();
            $table->longText('signature_dept2_image')->nullable();
            $table->timestamp('signature_dept2_at')->nullable();

            $table->string('status')->default('completed'); // 'draft' or 'completed'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'week_number']);
            $table->index('location');
            $table->index('checked_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cctv_regular_reports');
    }
};
