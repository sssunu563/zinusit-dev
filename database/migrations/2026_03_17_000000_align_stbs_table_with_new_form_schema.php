<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stbs')) {
            Schema::table('stbs', function (Blueprint $table) {
                if (!Schema::hasColumn('stbs', 'status')) {
                    $table->unsignedTinyInteger('status')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'it_drafter_id')) {
                    $table->unsignedBigInteger('it_drafter_id')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'it_checker_id')) {
                    $table->unsignedBigInteger('it_checker_id')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'it_approved_id')) {
                    $table->unsignedBigInteger('it_approved_id')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'group_id')) {
                    $table->unsignedBigInteger('group_id')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'use_date')) {
                    $table->date('use_date')->nullable()->index();
                }
                if (!Schema::hasColumn('stbs', 'batch_no')) {
                    $table->string('batch_no')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
    }
};
