<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to MS Teams via Webhook.
     */
    public static function sendToTeams(string $title, string $message, string $color = '003628', array $facts = [])
    {
        $webhookUrl = config('services.teams.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        // Format facts for Teams Adaptive Card or Message Card
        $formattedFacts = [];
        foreach ($facts as $label => $value) {
            $formattedFacts[] = [
                'name' => $label,
                'value' => $value
            ];
        }

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => $color,
            'summary' => $title,
            'sections' => [
                [
                    'activityTitle' => $title,
                    'activitySubtitle' => $message,
                    'facts' => $formattedFacts,
                    'markdown' => true
                ]
            ]
        ];

        try {
            Http::post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Teams Webhook Error: ' . $e->getMessage());
        }
    }

    /**
     * Send an email notification.
     */
    public static function sendEmail(string $subject, string $body, ?string $to = null)
    {
        $recipient = $to ?: config('services.notifications.admin_email', env('MAIL_FROM_ADDRESS'));

        if (!$recipient) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            });
        } catch (\Exception $e) {
            Log::error('Email Notification Error: ' . $e->getMessage());
        }
    }
}
