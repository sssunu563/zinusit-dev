<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('company', 120)->index();
            $table->string('location', 120)->index();
            $table->string('category', 120)->index();
            $table->string('priority', 32)->index();
            $table->string('requester', 120)->index();
            $table->string('department', 120)->index();
            $table->text('issue_description');
            $table->text('action_taken');
            $table->string('technician', 120)->index();
            $table->string('status', 32)->index();
            $table->date('date_closed')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};