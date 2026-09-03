<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\LdapService;
use App\Services\SnipeItManagedUserService;
use App\Services\SnipeItService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SnipeItManagedUserService $managedUsers,
        private readonly SnipeItService $snipe,
        private readonly LdapService $ldap,
    ) {
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user        = $request->user();
        $baseProfile = $this->managedUsers->getProfileForUser($user);

        // Fetch full Snipe-IT object for extra fields (mobile, website, notes, flags)
        $snipeExtra = [];
        $assets     = [];

        if ($user->snipeit_user_id) {
            $userId = $user->snipeit_user_id;

            // 1. Fetch History Hints (Metadata from local STB history)
            $localHistoryMap = \App\Models\StbItem::whereHas('stb', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereNotNull('snipeit_asset_id')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('snipeit_asset_id')
                ->map(function($group) {
                    return [
                        'serial'           => $group->first(fn($i) => !empty($i->serial_no))?->serial_no,
                        'inventory_number' => $group->first(fn($i) => !empty($i->inventory_number))?->inventory_number,
                    ];
                });

            // 2. Fetch Base Assets and Extra Data from Snipe-IT (parallel pool)
            $poolResults = $this->snipe->requestPool([
                'user'        => ["users/{$userId}", []],
                'hardware'    => ["users/{$userId}/assets",      []],
                'licenses'    => ["users/{$userId}/licenses",    []],
                'accessories' => ["users/{$userId}/accessories", []],
                'files'       => ["users/{$userId}/files",       []],
            ]);

            $raw = $poolResults['user'] ?? [];
            if (!empty($raw['id'])) {
                $snipeExtra = [
                    'mobile'               => $raw['mobile']               ?? null,
                    'website'              => $raw['website']               ?? null,
                    'notes'                => $raw['notes']                 ?? null,
                    'vip'                  => (bool) ($raw['vip']                  ?? false),
                    'remote'               => (bool) ($raw['remote']               ?? false),
                    'auto_assign_licenses' => (bool) ($raw['auto_assign_licenses'] ?? false),
                ];
            }

            $getNote = function($a) {
                $candidates = [
                    $a['pivot']['note'] ?? null,
                    $a['note']          ?? null,
                    $a['notes']         ?? null,
                    data_get($a, 'last_checkout.note'),
                ];
                foreach ($candidates as $c) {
                    if ($c !== null && trim((string)$c) !== '' && trim((string)$c) !== '-') return trim((string)$c);
                }
                return '';
            };

            $parseStructuredNote = function($note) {
                if (empty($note)) return [];
                $data  = [];
                $parts = explode('|', (string) $note);
                foreach ($parts as $part) {
                    $pair = explode(':', $part, 2);
                    if (count($pair) === 2) {
                        $key   = trim($pair[0]);
                        $value = trim($pair[1]);
                        $data[$key] = $value;
                        $lowKey = strtolower($key);
                        if (in_array($lowKey, ['sn', 's/n', 'serial']))  $data['SN']   = $value;
                        if (in_array($lowKey, ['stb', 'no stb']))        $data['STB']  = $value;
                        if (in_array($lowKey, ['item', 'product']))      $data['Item'] = $value;
                    }
                }
                return $data;
            };

            // PERF: Batch deep-note fetches for licenses + accessories in a single pool
            // instead of N serial API calls in a loop
            $licenseRows     = $poolResults['licenses']['rows']    ?? [];
            $accessoryRows   = $poolResults['accessories']['rows'] ?? [];

            $deepNotePool = [];
            foreach ($licenseRows as $a) {
                $snipeId = (int) ($a['id'] ?? 0);
                if ($snipeId > 0 && empty($getNote($a))) {
                    $deepNotePool["lic_{$snipeId}"] = ["licenses/{$snipeId}/checkedout", ['limit' => 50]];
                }
            }
            foreach ($accessoryRows as $a) {
                $snipeId = (int) ($a['id'] ?? 0);
                if ($snipeId > 0 && empty($getNote($a))) {
                    $deepNotePool["acc_{$snipeId}"] = ["accessories/{$snipeId}/checkedout", ['limit' => 50]];
                }
            }
            $deepNoteResults = !empty($deepNotePool) ? $this->snipe->requestPool($deepNotePool) : [];

            // Helper to extract note from deep-note pool result
            $resolveDeepNote = function(int $snipeId, string $prefix) use ($userId, $deepNoteResults, $parseStructuredNote) {
                $rows = $deepNoteResults["{$prefix}_{$snipeId}"]['rows'] ?? [];
                foreach ($rows as $co) {
                    if ((int)($co['assigned_to']['id'] ?? 0) === (int)$userId) {
                        $note = trim((string)($co['note'] ?? ''));
                        if ($note !== '') return [$note, $parseStructuredNote($note)];
                    }
                }
                return ['', []];
            };

            $transformItem = function($a, $type) use ($getNote, $parseStructuredNote, $localHistoryMap, $resolveDeepNote) {
                $rawNote    = $getNote($a);
                $structured = $parseStructuredNote($rawNote);
                $snipeId    = (int) ($a['id'] ?? 0);
                $localHint  = $localHistoryMap->get($snipeId);

                // Deep Note (already pre-fetched via pool for license/accessory)
                if (empty($rawNote) && in_array($type, ['license', 'accessory'])) {
                    $prefix = $type === 'license' ? 'lic' : 'acc';
                    [$rawNote, $structured] = $resolveDeepNote($snipeId, $prefix);
                }

                $mapped = [
                    'id'           => $snipeId,
                    'name'         => $structured['Item'] ?? ($a['name'] ?? ucfirst($type)),
                    'asset_tag'    => $structured['STB']  ?? (data_get($a, 'category.name') ?? '-'),
                    'serial'       => $structured['SN']   ?? ($a['serial'] ?? '-'),
                    'type'         => $type === 'accessory' ? 'accessory' : ($type === 'license' ? 'license' : ($type === 'consumable' ? 'consumable' : 'hardware')),
                    'image'        => $a['image'] ?? data_get($a, 'model.image'),
                    'notes'        => $rawNote,
                    'parsed_note'  => $structured,
                    'category'     => data_get($a, 'category.name'),
                    'manufacturer' => data_get($a, 'manufacturer.name'),
                    'created_at'   => data_get($a, 'created_at.formatted') ?? data_get($a, 'pivot.created_at'),
                ];

                // Merge History Hint
                if ($localHint) {
                    if (($mapped['serial'] === '-' || empty($mapped['serial'])) && !empty($localHint['serial'])) {
                        $mapped['serial'] = $localHint['serial'];
                    }
                    if (($mapped['asset_tag'] === '-' || empty($mapped['asset_tag'])) && !empty($localHint['inventory_number'])) {
                        $mapped['asset_tag'] = $localHint['inventory_number'];
                    }
                }
                return $mapped;
            };

            $assets = collect($poolResults['hardware']['rows'] ?? [])->map(fn($a) => $transformItem($a, 'hardware'))
                ->concat(collect($licenseRows)->map(fn($a) => $transformItem($a, 'license')))
                ->concat(collect($accessoryRows)->map(fn($a) => $transformItem($a, 'accessory')))
                ->values()->all();

            $consumables = collect(data_get($poolResults, 'consumables.rows', data_get($poolResults, 'consumables', [])))->map(fn($a) => $transformItem($a, 'consumable'))->values()->all();
            
            // Files can be direct array or wrapped in rows
            $rawFiles = data_get($poolResults, 'files.rows', $poolResults['files'] ?? []);
            $files = collect($rawFiles)->map(function($f) {
                return [
                    'id'         => $f['id']      ?? 0,
                    'filename'   => $f['filename'] ?? ($f['file'] ?? 'Unknown File'),
                    'file_url'   => $f['download_url'] ?? ($f['file_url'] ?? '#'),
                    'created_at' => data_get($f, 'created_at.formatted') ?? ($f['created_at'] ?? '-'),
                    'filesize'   => $f['filesize'] ?? ($f['size'] ?? null),
                ];
            })->all();
        }

        $profile = array_merge($baseProfile, $snipeExtra);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'profile'         => $profile,
            'assets'          => $assets,
            'consumables'     => $consumables ?? [],
            'files'           => $files        ?? [],
            // PERF: 'options' removed — was triggering 12+ heavy Snipe-IT API calls per page load.
            // Form options (managers/locations/departments/companies) are cached in SnipeItManagedUserService
            // and should only be fetched lazily when the edit form opens.
            // PERF: 'ldapLinked' now derived from local user attribute instead of live LDAP connection.
            'snipeitLinked'   => (bool) $user->snipeit_user_id,
            'ldapLinked'      => (bool) ($user->username),
            'status'          => $request->session()->get('status'),
        ]);
    }

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'image' => ['nullable', 'string'], // Expecting Base64 string or empty for deletion
        ]);

        $user = $request->user();
        if (!$user->snipeit_user_id) {
            return back()->with('error', 'User tidak terhubung ke Snipe-IT.');
        }

        $imageData = $request->input('image');

        // Handle Deletion
        if (empty($imageData)) {
            $response = $this->snipe->updateRecord('users', (int) $user->snipeit_user_id, [
                'avatar' => null,
            ]);

            if (($response['status'] ?? 'error') === 'success') {
                $user->update(['avatar' => null]);
                return back()->with('status', 'Foto profil berhasil dihapus.');
            }
            return back()->with('error', 'Gagal menghapus foto profil.');
        }

        // Handle Base64 Upload
        try {
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $extension = strtolower($type[1]);
                if (!in_array($extension, ['jpg', 'jpeg', 'gif', 'png'])) {
                    return back()->with('error', 'Format gambar tidak didukung (gunakan JPG, PNG, atau GIF).');
                }

                $base64String = substr($imageData, strpos($imageData, ',') + 1);
                $binaryData = base64_decode($base64String);

                if ($binaryData === false) {
                    return back()->with('error', 'Gagal memproses data gambar.');
                }

                $filename = "avatar_{$user->id}_" . time() . '.' . $extension;
                $response = $this->snipe->updateAvatar((int) $user->snipeit_user_id, $binaryData, $filename);

                if (($response['status'] ?? 'error') === 'success') {
                    // Refresh local avatar from Snipe-IT to keep sidebar in sync
                    $freshRemote = $this->snipe->getUser((int) $user->snipeit_user_id);
                    if ($freshRemote && isset($freshRemote['avatar'])) {
                        $user->update(['avatar' => $freshRemote['avatar']]);
                    }

                    return back()->with('status', 'Foto profil berhasil diperbarui.');
                }

                $errMsg = $response['messages']['api'][0] ?? $response['messages'] ?? 'Gagal mengunggah ke Snipe-IT.';
                return back()->with('error', 'Gagal memperbarui foto profil: ' . (is_array($errMsg) ? implode(', ', $errMsg) : $errMsg));
            }
            
            return back()->with('error', 'Format data gambar tidak valid.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
