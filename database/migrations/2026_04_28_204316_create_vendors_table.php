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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->index();
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('category', 64)->nullable()->index(); // hardware, software, network, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
