<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->string('completed_pdf_path')->nullable()->after('requester_dept_head_signed_at');
            $table->timestamp('completed_at')->nullable()->after('completed_pdf_path');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->dropIndex(['completed_at']);
            $table->dropColumn(['completed_pdf_path', 'completed_at']);
        });
    }
};
