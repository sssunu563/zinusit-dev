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
        if (Schema::hasTable('procurements')) {
            $requiredColumns = [
                'id',
                'title',
                'request_number',
                'requester_name',
                'department',
                'estimated_cost',
                'actual_cost',
                'status',
                'request_date',
                'purchase_date',
                'po_number',
                'description',
                'vendor_id',
                'created_by',
                'created_at',
                'updated_at',
            ];

            if (! Schema::hasColumns('procurements', $requiredColumns)) {
                $missingColumns = array_diff($requiredColumns, Schema::getColumnListing('procurements'));

                throw new RuntimeException(
                    'Existing procurements table is missing columns: '.implode(', ', $missingColumns)
                );
            }

            if (! Schema::hasIndex('procurements', ['request_number'], 'unique')) {
                Schema::table('procurements', function (Blueprint $table): void {
                    $table->unique('request_number');
                });
            }

            return;
        }

        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('request_number')->unique();
            $table->string('requester_name');
            $table->string('department');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->string('status')->default('Pending'); // Pending, Approved, Purchased, Cancelled
            $table->date('request_date');
            $table->date('purchase_date')->nullable();
            $table->string('po_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
