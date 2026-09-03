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
        Schema::create('peminjamans', function (Blueprint $table) {
            $table->id();
            $table->integer('status')->nullable();
            $table->string('document_type')->default('loan');
            $table->string('movement_type')->nullable();
            $table->unsignedBigInteger('linked_stb_id')->nullable()->index();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('it_drafter_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_company')->nullable();
            $table->string('user_dept')->nullable();
            $table->string('user_title')->nullable();
            $table->string('user_phone')->nullable();
            $table->string('user_email')->nullable();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->string('location_name')->nullable();
            $table->date('use_date')->nullable();
            $table->string('photo')->nullable();
            $table->text('remark')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->text('it_drafter_signature_path')->nullable();
            $table->timestamp('it_drafter_signed_at')->nullable();
            $table->text('requester_received_signature_path')->nullable();
            $table->timestamp('requester_received_signed_at')->nullable();
            $table->string('completed_pdf_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('peminjaman_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peminjaman_id')->constrained('peminjamans')->onDelete('cascade');
            $table->string('nama');
            $table->string('kategori')->nullable();
            $table->string('type');
            $table->integer('jumlah');
            $table->string('serial_no')->nullable();
            $table->string('inventory_number')->nullable();
            $table->unsignedBigInteger('computer_id')->nullable();
            $table->unsignedBigInteger('snipeit_asset_id')->nullable();
            $table->text('asset_reference_snapshot')->nullable();
            $table->string('condition')->default('Good');
            $table->timestamps();
        });

        Schema::create('peminjaman_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peminjaman_id')->constrained('peminjamans')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peminjaman_attachments');
        Schema::dropIfExists('peminjaman_items');
        Schema::dropIfExists('peminjamans');
    }

};
