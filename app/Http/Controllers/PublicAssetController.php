<?php

namespace App\Http\Controllers;

use App\Models\Stb;
use App\Services\SnipeItService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class PublicAssetController extends Controller
{
    public function __construct(
        protected SnipeItService $snipeIt
    ) {}

    public function show(Request $request, string $serial)
    {
        // 1. Check if it's an STB ID (formatted STB-XXX-XXXX-ID or raw ID)
        $stb = null;
        if (is_numeric($serial)) {
            $stb = Stb::find($serial);
        } else if (str_starts_with($serial, 'STB-')) {
            $parts = explode('-', $serial);
            $id = end($parts);
            if (is_numeric($id)) {
                $stb = Stb::find($id);
            }
        }

        if ($stb) {
            return $this->handleStbPublic($request, $stb);
        }

        // 2. Fallback to Snipe-IT Asset lookup by Serial
        $asset = $this->snipeIt->getHardwareBySerial($serial);

        if (!$asset || empty($asset['rows'])) {
            abort(404, 'Asset/Document not found');
        }

        $assetData = $asset['rows'][0];
        $assetId = $assetData['id'];

        // Fetch components
        $components = $this->snipeIt->request("hardware/{$assetId}/components")['rows'] ?? [];
        
        return Inertia::render('Public/AssetShow', [
            'asset' => [
                'name' => $assetData['name'],
                'asset_tag' => $assetData['asset_tag'],
                'serial' => $assetData['serial'],
                'model' => $assetData['model']['name'] ?? 'Unknown',
                'image' => $assetData['image'] ?? null,
                'status' => $assetData['status_label']['name'] ?? 'Unknown',
                'assigned_to' => $assetData['assigned_to']['name'] ?? 'Available',
                'location' => $assetData['location']['name'] ?? 'Warehouse',
                'components' => array_map(fn($c) => [
                    'name' => $c['name'],
                    'category' => $c['category']['name'] ?? null,
                    'qty' => $c['qty'] ?? 1,
                ], $components),
            ]
        ]);
    }

    protected function handleStbPublic(Request $request, Stb $stb)
    {
        $sessionKey = "stb_verified_{$stb->id}";
        
        if (!Session::get($sessionKey)) {
            return Inertia::render('Public/Verify', [
                'id' => $stb->id,
                'type' => 'stb',
            ]);
        }

        return Inertia::render('Public/StbShow', [
            'stb' => $stb->load('items'),
        ]);
    }

    public function verify(Request $request, string $id)
    {
        $stb = Stb::findOrFail($id);
        $pin = $request->input('pin');
        
        // Verification: Last 4 digits of recipient's phone number
        $phone = preg_replace('/[^0-9]/', '', $stb->user_phone ?: '');
        
        if (strlen($phone) < 4) {
            $last4 = '0000';
        } else {
            $last4 = substr($phone, -4);
        }

        if ($pin === $last4) {
            Session::put("stb_verified_{$stb->id}", true);
            return response()->json(['success' => true]);
        }

        return response()->json([
            'message' => 'PIN salah. Silakan masukkan 4 digit terakhir nomor telepon penerima yang terdaftar di STB.'
        ], 422);
    }

    /**
     * Display the search form for My Assets.
     */
    public function checkAssets(Request $request)
    {
        return Inertia::render('Public/CheckAssets');
    }

    /**
     * Fetch assets assigned to a specific email.
     */
    public function fetchAssetsByEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        // 1. Search for user in Snipe-IT by email
        $userResponse = $this->snipeIt->request('users', ['search' => $email]);

        if (empty($userResponse['rows'])) {
            return response()->json([
                'message' => 'Email tidak terdaftar di sistem manajemen aset.'
            ], 404);
        }

        $snipeUser = collect($userResponse['rows'])->firstWhere('email', $email);

        if (!$snipeUser) {
            return response()->json([
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        $userId = $snipeUser['id'];

        // 2. Fetch all assets concurrently
        $pool = $this->snipeIt->requestPool([
            'hardware'    => ["users/{$userId}/assets",      []],
            'licenses'    => ["users/{$userId}/licenses",    []],
            'accessories' => ["users/{$userId}/accessories", []],
            'consumables' => ["users/{$userId}/consumables", []],
        ]);

        $hardware    = $pool['hardware']['rows']    ?? (is_array($pool['hardware'] ?? null)    ? $pool['hardware']    : []);
        $licenses    = $pool['licenses']['rows']    ?? (is_array($pool['licenses'] ?? null)    ? $pool['licenses']    : []);
        $accessories = $pool['accessories']['rows'] ?? (is_array($pool['accessories'] ?? null) ? $pool['accessories'] : []);
        $consumables = $pool['consumables']['rows'] ?? (is_array($pool['consumables'] ?? null) ? $pool['consumables'] : []);

        $assets = collect($hardware)->map(fn($a) => [
            'name'     => $a['name'] ?? $a['model']['name'] ?? 'Hardware',
            'tag'      => $a['asset_tag'] ?? '-',
            'serial'   => $a['serial'] ?? '-',
            'type'     => 'Hardware',
            'category' => $a['category']['name'] ?? '-',
            'status'   => $a['status_label']['name'] ?? '-',
            'location' => $a['location']['name'] ?? '-',
            'model'    => $a['model']['name'] ?? '-',
            'image'    => $a['image'] ?? null,
            'checkout_at' => $a['last_checkout']['formatted'] ?? null,
        ])->concat(collect($licenses)->map(fn($a) => [
            'name'     => $a['name'],
            'tag'      => '-',
            'serial'   => $a['serial'] ?? '-',
            'type'     => 'License',
            'category' => $a['category']['name'] ?? 'License',
            'status'   => 'Active',
            'location' => '-',
            'model'    => '-',
            'image'    => null,
            'checkout_at' => null,
        ]))->concat(collect($accessories)->map(fn($a) => [
            'name'     => $a['name'],
            'tag'      => '-',
            'serial'   => $a['serial'] ?? '-',
            'type'     => 'Accessory',
            'category' => $a['category']['name'] ?? 'Accessory',
            'status'   => 'Assigned',
            'location' => $a['location']['name'] ?? '-',
            'model'    => '-',
            'image'    => $a['image'] ?? null,
            'checkout_at' => null,
        ]))->concat(collect($consumables)->map(fn($a) => [
            'name'     => $a['name'],
            'tag'      => '-',
            'serial'   => $a['serial'] ?? '-',
            'type'     => 'Consumable',
            'category' => $a['category']['name'] ?? 'Consumable',
            'status'   => 'Assigned',
            'location' => $a['location']['name'] ?? '-',
            'model'    => '-',
            'image'    => $a['image'] ?? null,
            'checkout_at' => null,
        ]));

        return response()->json([
            'user' => [
                'name'       => $snipeUser['name'],
                'email'      => $snipeUser['email'],
                'avatar'     => $snipeUser['avatar'] ?? null,
                'department' => $snipeUser['department']['name'] ?? null,
                'jobtitle'   => $snipeUser['jobtitle'] ?? null,
                'location'   => $snipeUser['location']['name'] ?? null,
            ],
            'assets' => $assets->values()->all(),
        ]);
    }
}
