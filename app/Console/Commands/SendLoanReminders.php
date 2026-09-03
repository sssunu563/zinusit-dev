<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stb;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendLoanReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loan:reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for overdue or approaching loan returns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue/approaching loans...');

        // 1. Approaching (Tomorrow)
        $approaching = Stb::query()
            ->with('items')
            ->where('document_type', 'loan')
            ->where('movement_type', 'out')
            ->where('is_completed', true)
            ->whereNull('returned_at')
            ->whereDate('expected_return_date', Carbon::tomorrow())
            ->get();

        foreach ($approaching as $stb) {
            $assetNames = $stb->items->pluck('nama')->implode(', ');
            $message = "Pinjaman akan jatuh tempo besok: {$stb->user_name} ({$assetNames})";
            $this->notifyAdmins($stb, $message, 'warning');
            $this->notifyTeams($stb, "⚠️ Loan Approaching Deadline", $message, 'E29627');
            $this->notifyEmail($stb, "⚠️ Loan Approaching Deadline", $message);
        }

        // 2. Overdue
        $overdue = Stb::query()
            ->with('items')
            ->where('document_type', 'loan')
            ->where('movement_type', 'out')
            ->where('is_completed', true)
            ->whereNull('returned_at')
            ->whereDate('expected_return_date', '<', now())
            ->get();

        foreach ($overdue as $stb) {
            $assetNames = $stb->items->pluck('nama')->implode(', ');
            $deadline = $stb->expected_return_date ? $stb->expected_return_date->format('d M Y') : '-';
            $message = "Pinjaman OVERDUE: {$stb->user_name} ({$assetNames}) (Deadline: {$deadline})";
            $this->notifyAdmins($stb, $message, 'danger');
            $this->notifyTeams($stb, "🚨 Overdue Loan Alert", $message, 'A24432');
            $this->notifyEmail($stb, "🚨 Overdue Loan Alert", $message);
        }

        $this->info('Loan reminders processed.');
    }

    /**
     * Notify all admin users about a loan status.
     */
    protected function notifyAdmins($stb, $message, $tone)
    {
        $this->line("Notifying admins for STB #{$stb->id}: $message");

        // Log the event
        Log::info("Loan Reminder Notification: $message", ['stb_id' => $stb->id]);

        // Get all admin users
        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            AppNotification::create([
                'user_id' => $admin->id,
                'title'   => 'Pengingat Peminjaman',
                'message' => $message,
                'link'    => route('peminjaman.show', $stb->id),
                'tone'    => $tone,
                'icon'    => 'Clock',
            ]);
        }
    }

    protected function notifyTeams($stb, $title, $message, $color)
    {
        $facts = [
            'User' => $stb->user_name ?: 'Unknown',
            'Deadline' => $stb->expected_return_date ? $stb->expected_return_date->format('d M Y') : 'Not Set',
            'Items' => $stb->items->pluck('nama')->implode(', '),
        ];

        NotificationService::sendToTeams($title, $message, $color, $facts);
    }

    /**
     * Send notification to Email.
     */
    protected function notifyEmail($stb, $subject, $message)
    {
        $body = "{$message}\n\n";
        $body .= "Details:\n";
        $body .= "- User: " . ($stb->user_name ?: 'Unknown') . "\n";
        $body .= "- Deadline: " . ($stb->expected_return_date ? $stb->expected_return_date->format('d M Y') : 'Not Set') . "\n";
        $body .= "- Items: " . ($stb->items->pluck('nama')->implode(', ')) . "\n";
        $body .= "- Link: " . route('peminjaman.show', $stb->id) . "\n";

        NotificationService::sendEmail($subject, $body);
    }
}
