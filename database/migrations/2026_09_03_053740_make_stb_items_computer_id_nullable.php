<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Make computer_id nullable for components that may not be attached to hardware yet.
     * Components (like Mouse, Keyboard) are attached to a parent hardware (PC/Laptop).
     * This field allows users to assign components to their parent hardware during STB/Peminjaman creation.
     */
    public function up(): void
    {
        Schema::table('stb_items', function (Blueprint $table) {
            // Make computer_id nullable if it exists and isn't already nullable
            if (Schema::hasColumn('stb_items', 'computer_id')) {
                $table->unsignedBigInteger('computer_id')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stb_items', function (Blueprint $table) {
            if (Schema::hasColumn('stb_items', 'computer_id')) {
                $table->unsignedBigInteger('computer_id')->nullable(false)->change();
            }
        });
    }
};
