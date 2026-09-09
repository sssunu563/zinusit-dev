<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\NotificationWebhookSetting;
use App\Models\NotificationWebhookTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    private const EVENTS = [
        'approval_requested' => ['title' => 'Approval menunggu tindakan', 'message' => '{user} perlu memproses approval {document}.'],
        'approval_approved' => ['title' => 'Approval disetujui', 'message' => 'Approval {document} telah disetujui oleh {user}.'],
        'approval_rejected' => ['title' => 'Approval ditolak', 'message' => 'Approval {document} ditolak oleh {user}.'],
        'document_completed' => ['title' => 'Dokumen selesai', 'message' => 'Dokumen {document} telah selesai diproses oleh {user}.'],
        'asset_checked_out' => ['title' => 'Asset dipinjamkan', 'message' => 'Asset {asset} dipinjamkan kepada {user}.'],
        'asset_checked_in' => ['title' => 'Asset dikembalikan', 'message' => 'Asset {asset} telah dikembalikan oleh {user}.'],
    ];

    public function index(): Response
    {
        $setting = NotificationWebhookSetting::first();
        foreach (self::EVENTS as $event => $defaults) {
            NotificationWebhookTemplate::firstOrCreate(
                ['event' => $event],
                ['title' => $defaults['title'], 'message' => $defaults['message'], 'enabled' => true],
            );
        }

        return Inertia::render('Tools/NotificationSettings', [
            'settings' => [
                'enabled' => (bool) ($setting?->enabled ?? false),
                'provider' => $setting?->provider ?? 'custom',
                'hasWebhookUrl' => filled($setting?->webhook_url),
            ],
            'templates' => NotificationWebhookTemplate::query()
                ->whereIn('event', array_keys(self::EVENTS))
                ->orderBy('id')
                ->get(['event', 'enabled', 'title', 'message'])
                ->values()
                ->all(),
            'availableVariables' => ['{user}', '{document}', '{asset}', '{status}', '{link}'],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'provider' => ['required', 'in:custom,teams,slack,discord'],
            'webhook_url' => ['nullable', 'url', 'max:2000'],
            'templates' => ['required', 'array'],
            'templates.*.event' => ['required', 'in:' . implode(',', array_keys(self::EVENTS))],
            'templates.*.enabled' => ['boolean'],
            'templates.*.title' => ['required', 'string', 'max:255'],
            'templates.*.message' => ['required', 'string', 'max:2000'],
        ]);

        $setting = NotificationWebhookSetting::firstOrNew(['id' => 1]);
        $setting->fill([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'provider' => $data['provider'],
        ]);

        if (array_key_exists('webhook_url', $data) && filled($data['webhook_url'])) {
            $setting->webhook_url = $data['webhook_url'];
        }

        $setting->save();

        foreach ($data['templates'] as $template) {
            NotificationWebhookTemplate::updateOrCreate(
                ['event' => $template['event']],
                [
                    'enabled' => (bool) ($template['enabled'] ?? false),
                    'title' => $template['title'],
                    'message' => $template['message'],
                ],
            );
        }

        return to_route('notification-settings.index')
            ->with('status', 'Pengaturan webhook berhasil disimpan.');
    }

    public function testLocal(Request $request): RedirectResponse
    {
        AppNotification::create([
            'user_id' => $request->user()->id,
            'title' => 'Notifikasi testing',
            'message' => 'Notifikasi personal berhasil dibuat untuk akun Anda.',
            'link' => '/notification-settings',
            'tone' => 'success',
            'icon' => 'bell',
        ]);

        return back()->with('status', 'Notifikasi lokal berhasil dibuat. Buka ikon bell untuk melihatnya.');
    }

    public function testWebhook(Request $request): RedirectResponse
    {
        $setting = NotificationWebhookSetting::first();

        if (! $setting?->enabled || ! filled($setting->webhook_url)) {
            return back()->withErrors(['webhook_url' => 'Aktifkan webhook dan isi URL terlebih dahulu.']);
        }

        $event = $request->validate([
            'event' => ['required', 'in:' . implode(',', array_keys(self::EVENTS))],
        ])['event'];
        $template = NotificationWebhookTemplate::where('event', $event)->firstOrFail();
        $variables = [
            '{user}' => $request->user()?->name ?? 'User Testing',
            '{document}' => 'TEST-001',
            '{asset}' => 'ASSET-TEST-001',
            '{status}' => 'testing',
            '{link}' => url('/notification-settings'),
        ];
        $title = strtr($template->title, $variables);
        $message = strtr($template->message, $variables);
        $payload = $setting->provider === 'teams'
            ? [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'themeColor' => '003628',
                'summary' => $title,
                'sections' => [[
                    'activityTitle' => $title,
                    'activitySubtitle' => $message,
                    'markdown' => true,
                ]],
            ]
            : [
                'event' => $event,
                'title' => $title,
                'message' => $message,
                'timestamp' => now()->toIso8601String(),
            ];

        $response = Http::withOptions([
            'verify' => $this->webhookCaBundle(),
        ])->timeout(10)->post($setting->webhook_url, $payload);

        if ($response->failed()) {
            return back()->withErrors(['webhook_url' => 'Webhook gagal merespons dengan status HTTP ' . $response->status() . '.']);
        }

        return back()->with('status', 'Test webhook berhasil dikirim.');
    }

    private function webhookCaBundle(): string|bool
    {
        $configured = (string) env('WEBHOOK_CA_BUNDLE', '');
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        foreach ([
            'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\certs\\ca-bundle.crt',
            'C:\\Program Files\\Git\\usr\\ssl\\certs\\ca-bundle.crt',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return true;
    }
}
