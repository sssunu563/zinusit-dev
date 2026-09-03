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
        Schema::create('stb_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stb_id')->constrained('stbs')->onDelete('cascade');
            $table->string('nama')->index();
            $table->string('type')->index();
            $table->integer('jumlah')->index();
            $table->string('serial_no')->index();
            $table->unsignedBigInteger('computer_id')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stb_items');
    }
};
