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
        Schema::create('audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_session_id')->constrained('audit_sessions')->cascadeOnDelete();
            $table->integer('snipeit_asset_id')->index();
            $table->string('asset_tag', 64)->index();
            $table->string('serial', 120)->index();
            
            // Physical data captured
            $table->string('status', 32)->index(); // Match, Missing, Wrong Location, Wrong User, Broken
            $table->string('physical_location', 120)->nullable();
            $table->string('physical_user', 120)->nullable();
            $table->text('notes')->nullable();
            
            // Expected data at time of audit
            $table->string('expected_location', 120)->nullable();
            $table->string('expected_user', 120)->nullable();
            
            $table->foreignId('verified_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_items');
    }
};
