<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_stock_histories', function (Blueprint $table) {
            $table->id();
            $table->string('asset_type', 32);
            $table->unsignedBigInteger('asset_id');
            $table->integer('qty');
            $table->string('po_number', 100);
            $table->date('purchase_date');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_type', 'asset_id']);
            $table->index('purchase_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_stock_histories');
    }
};
