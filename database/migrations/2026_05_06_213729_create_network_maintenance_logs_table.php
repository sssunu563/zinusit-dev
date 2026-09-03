<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('network_devices')->cascadeOnDelete();

            // Ticket-style status: open = masih bermasalah, closed = sudah normal
            $table->enum('status', ['open', 'closed'])->default('open')->index();

            // Kapan mulai bermasalah
            $table->date('started_at');

            // Kapan selesai (null = masih open)
            $table->date('resolved_at')->nullable();

            // Jenis kejadian: maintenance, restart, down, auto_detected
            $table->string('event_type', 30)->default('maintenance');

            // Catatan bebas
            $table->text('notes')->nullable();

            // Auto-created by system (e.g. uptime 0%)
            $table->boolean('is_auto')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['started_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_maintenance_logs');
    }
};
