<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            if (!Schema::hasColumn('stbs', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('is_completed');
                $table->index('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            if (Schema::hasColumn('stbs', 'cancelled_at')) {
                $table->dropIndex(['cancelled_at']);
                $table->dropColumn('cancelled_at');
            }
        });
    }
};