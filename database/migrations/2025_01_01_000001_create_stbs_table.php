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
        Schema::create('stbs', function (Blueprint $table) {
            $table->id();
            $table->date('deliver_date')->nullable()->index();
            $table->unsignedTinyInteger('status')->nullable()->index();
            $table->unsignedBigInteger('it_drafter_id')->nullable()->index();
            $table->unsignedBigInteger('it_checker_id')->nullable()->index();
            $table->unsignedBigInteger('it_approved_id')->nullable()->index();
            $table->string('req_doc_no')->nullable()->index();
            $table->string('po_doc_no')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->string('building')->nullable()->index();
            $table->date('use_date')->nullable()->index();
            $table->string('batch_no')->nullable()->index();
            $table->string('photo')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stbs');
    }
};
