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
        Schema::create('stb_attachments', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('stb_id')->constrained()->cascadeOnDelete();
            $blueprint->string('file_path');
            $blueprint->string('file_type')->nullable(); // e.g. image/png, image/jpeg
            $blueprint->text('notes')->nullable();
            $blueprint->timestamps();
        });

        // Add expected_return_date to stbs table as well to support tracking
        Schema::table('stbs', function (Blueprint $table) {
            $table->date('expected_return_date')->nullable()->after('use_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stb_attachments');

        Schema::table('stbs', function (Blueprint $table) {
            $table->dropColumn('expected_return_date');
        });
    }
};
