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
        Schema::create('snipeit_users', function (Blueprint $table) {
            $table->id();
            $table->string('snipeit_id')->unique();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('jobtitle')->nullable();
            $table->string('employee_num')->nullable();
            
            // Manager
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('manager_name')->nullable();
            
            // Location
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('location_name')->nullable();
            
            // Department
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department')->nullable();
            
            // Company
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('company')->nullable();
            
            // Status & metadata
            $table->boolean('activated')->default(false);
            $table->string('avatar')->nullable();
            $table->json('raw_data')->nullable();
            
            // Timestamps
            $table->timestamp('snipeit_created_at')->nullable();
            $table->timestamp('snipeit_updated_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('snipeit_id');
            $table->index('username');
            $table->index('email');
            $table->index('name');
            $table->index('company_id');
            $table->index('location_id');
            $table->index('department_id');
            $table->index(['company', 'department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snipeit_users');
    }
};
