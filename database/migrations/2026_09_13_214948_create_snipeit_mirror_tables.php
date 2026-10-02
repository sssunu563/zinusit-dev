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
        // Status Labels - untuk filter status assets
        Schema::create('snipeit_status_labels', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('status_type')->nullable(); // deployable, pending, archived, undeployable
            $table->json('raw_data')->nullable(); // simpan full response dari API
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('status_type');
        });

        // Categories - untuk grouping asset types
        Schema::create('snipeit_categories', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('category_type')->nullable(); // asset, accessory, consumable, component, license
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('category_type');
        });

        // Locations - untuk tracking lokasi assets
        Schema::create('snipeit_locations', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // Users - untuk assigned user info
        Schema::create('snipeit_users', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('department')->nullable();
            $table->string('company')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('username');
            $table->index('email');
        });

        // Hardware Assets - tabel utama untuk assets
        Schema::create('snipeit_assets', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name')->nullable();
            $table->string('asset_tag')->nullable();
            $table->string('serial')->nullable();
            $table->string('model_name')->nullable();
            $table->unsignedInteger('status_id')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('assigned_to')->nullable(); // user_id
            $table->string('assigned_type')->nullable(); // user, asset, location
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable(); // full JSON dari API
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('status_id');
            $table->index('category_id');
            $table->index('location_id');
            $table->index('assigned_to');
            $table->index('asset_tag');
            $table->index('serial');
        });

        // Consumables
        Schema::create('snipeit_consumables', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->unsignedInteger('category_id')->nullable();
            $table->integer('qty')->default(0);
            $table->integer('remaining')->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('category_id');
        });

        // Licenses
        Schema::create('snipeit_licenses', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('product_key')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->integer('seats')->default(0);
            $table->integer('free_seats_count')->default(0);
            $table->date('expiration_date')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('category_id');
            $table->index('expiration_date');
        });

        // Accessories
        Schema::create('snipeit_accessories', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->unsignedInteger('category_id')->nullable();
            $table->integer('qty')->default(0);
            $table->integer('remaining_qty')->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('category_id');
        });

        // Components
        Schema::create('snipeit_components', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->unsignedInteger('category_id')->nullable();
            $table->integer('qty')->default(0);
            $table->integer('remaining')->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snipeit_components');
        Schema::dropIfExists('snipeit_accessories');
        Schema::dropIfExists('snipeit_licenses');
        Schema::dropIfExists('snipeit_consumables');
        Schema::dropIfExists('snipeit_assets');
        Schema::dropIfExists('snipeit_users');
        Schema::dropIfExists('snipeit_locations');
        Schema::dropIfExists('snipeit_categories');
        Schema::dropIfExists('snipeit_status_labels');
    }
};
