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
        Schema::table('stbs', function (Blueprint $table) {
            $table->string('user_name')->nullable()->after('user_id');
            $table->string('user_company')->nullable()->after('user_name');
            $table->string('user_dept')->nullable()->after('user_company');
            $table->string('user_title')->nullable()->after('user_dept');
            $table->string('user_phone')->nullable()->after('user_title');
            $table->string('user_email')->nullable()->after('user_phone');
            $table->string('location_name')->nullable()->after('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stbs', function (Blueprint $table) {
            $table->dropColumn([
                'user_name',
                'user_company',
                'user_dept',
                'user_title',
                'user_phone',
                'user_email',
                'location_name'
            ]);
        });
    }
};
