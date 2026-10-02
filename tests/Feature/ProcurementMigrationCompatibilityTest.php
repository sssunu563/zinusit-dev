<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcurementMigrationCompatibilityTest extends TestCase
{
    public function test_it_adds_the_missing_unique_index_to_an_existing_procurements_table(): void
    {
        Schema::dropIfExists('procurements');
        Schema::create('procurements', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('request_number');
            $table->string('requester_name');
            $table->string('department');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->string('status')->default('Pending');
            $table->date('request_date');
            $table->date('purchase_date')->nullable();
            $table->string('po_number')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        try {
            $migration = require database_path('migrations/2026_04_28_215301_create_procurements_table.php');
            $migration->up();

            $this->assertTrue(Schema::hasIndex('procurements', ['request_number'], 'unique'));
        } finally {
            Schema::dropIfExists('procurements');
        }
    }
}
