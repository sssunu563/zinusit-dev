<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_webhook_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event')->unique();
            $table->boolean('enabled')->default(true);
            $table->string('title');
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_webhook_templates');
    }
};
