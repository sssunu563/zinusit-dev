<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->unsignedBigInteger('snipeit_user_id')->nullable()->unique()->after('password');
            $table->string('snipeit_username')->nullable()->after('snipeit_user_id');
            $table->timestamp('snipeit_synced_at')->nullable()->after('snipeit_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['snipeit_user_id']);
            $table->dropColumn([
                'username',
                'snipeit_user_id',
                'snipeit_username',
                'snipeit_synced_at',
            ]);
        });
    }
};