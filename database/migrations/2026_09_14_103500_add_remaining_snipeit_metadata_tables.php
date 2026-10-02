<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds remaining metadata tables that weren't in the initial mirror:
     * - snipeit_models (for asset models with fieldsets)
     * - snipeit_manufacturers (for manufacturers)
     * - snipeit_suppliers (for suppliers)
     * - snipeit_companies (for companies)
     * - snipeit_fieldsets (for custom fieldsets)
     * 
     * These complement existing tables (users, locations, categories, status_labels, assets)
     */
    public function up(): void
    {
        // Models table (from Snipe-IT /models endpoint)
        Schema::create('snipeit_models', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('model_number')->nullable();
            $table->unsignedInteger('manufacturer_id')->nullable();
            $table->string('manufacturer_name')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->unsignedInteger('fieldset_id')->nullable();
            $table->string('fieldset_name')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('manufacturer_id');
            $table->index('category_id');
            $table->index('fieldset_id');
        });

        // Manufacturers table (from Snipe-IT /manufacturers endpoint)
        Schema::create('snipeit_manufacturers', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('support_url')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('support_email')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        // Suppliers table (from Snipe-IT /suppliers endpoint)
        Schema::create('snipeit_suppliers', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip')->nullable();
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('contact')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        // Companies table (from Snipe-IT /companies endpoint)
        Schema::create('snipeit_companies', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        // Fieldsets table (from Snipe-IT /fieldsets endpoint)
        Schema::create('snipeit_fieldsets', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->json('fields')->nullable(); // Array of custom fields
            $table->json('raw_data')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snipeit_fieldsets');
        Schema::dropIfExists('snipeit_companies');
        Schema::dropIfExists('snipeit_suppliers');
        Schema::dropIfExists('snipeit_manufacturers');
        Schema::dropIfExists('snipeit_models');
    }
};
