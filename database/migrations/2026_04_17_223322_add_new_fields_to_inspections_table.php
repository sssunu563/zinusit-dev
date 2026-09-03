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
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('email')->nullable()->after('user');
            $table->string('leader')->nullable()->after('email');
            $table->text('asset_snapshot')->nullable()->after('device_name'); // JSON snapshot
            $table->string('approve_by')->nullable()->after('checked_by');
            $table->text('it_signature')->nullable()->after('photo');       // base64 canvas
            $table->text('user_signature')->nullable()->after('it_signature');
            $table->text('leader_signature')->nullable()->after('user_signature');
            $table->date('signature_date')->nullable()->after('leader_signature');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn([
                'email', 'leader', 'asset_snapshot', 'approve_by',
                'it_signature', 'user_signature', 'leader_signature', 'signature_date',
            ]);
        });
    }
};
