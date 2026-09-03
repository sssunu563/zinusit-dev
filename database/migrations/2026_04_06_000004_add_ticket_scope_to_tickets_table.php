<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_scope', 32)
                ->default('general')
                ->after('category')
                ->index();
        });

        DB::table('tickets')
            ->whereNull('ticket_scope')
            ->update(['ticket_scope' => 'general']);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('ticket_scope');
        });
    }
};