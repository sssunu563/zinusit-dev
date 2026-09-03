<?php

namespace App\Http\Controllers;

use App\Services\SnipeItService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnipeItController extends Controller
{
    private const ASSET_TYPE_LABELS = [
        'assets' => 'Assets',
        'license' => 'License',
        'accessories' => 'Accessories',
        'consumable' => 'Consumable',
        'component' => 'Component',
        'asset' => 'Assets',
        'computer' => 'Computer',
        'monitor' => 'Monitor',
        'network' => 'Network Device',
        'consumables' => 'Consumables',
        'cctv' => 'CCTV',
        'nvr' => 'NVR',
    ];

    public function __construct(
        private readonly SnipeItService $snipe,
    ) {
    }

    public function users(): JsonResponse
    {
        return response()->json(
            collect($this->snipe->fetchRows('users'))
                ->map(fn (array $user) => $this->transformUser($user))
                ->values()
                ->all(),
        );
    }

    public function locations(): JsonResponse
    {
        return response()->json(
            collect($this->snipe->fetchRows('locations'))
                ->map(fn (array $location) => [
                    'id' => (int) ($location['id'] ?? 0),
                    'name' => (string) ($location['name'] ?? '-'),
                    'completename' => (string) ($location['name'] ?? '-'),
                ])
                ->filter(fn (array $location) => $location['id'] > 0)
                ->values()
                ->all(),
        );
    }

    public function userTitles(): JsonResponse
    {
        $titles = collect($this->snipe->fetchRows('users'))
            ->pluck('jobtitle')
            ->map(fn ($title) => trim((string) $title))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $title, int $index) => [
                'id' => $index + 1,
                'name' => $title,
            ])
            ->all();

        return response()->json($titles);
    }

    public function statuses(): JsonResponse
    {
        return response()->json(
            collect($this->snipe->fetchRows('statuslabels'))
                ->map(fn (array $status) => [
                    'id' => (int) ($status['id'] ?? 0),
                    'name' => (string) ($status['name'] ?? '-'),
                ])
                ->filter(fn (array $status) => $status['id'] > 0)
                ->values()
                ->all(),
        );
    }

    public function createModel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'category_id' => 'required|integer',
            'manufacturer_id' => 'nullable|integer',
            'fieldset_id' => 'nullable|integer',
        ]);

        $payload = array_filter([
            'name' => trim((string) $validated['name']),
            'model_number' => trim((string) ($validated['model_number'] ?? '')) ?: null,
            'category_id' => (int) $validated['category_id'],
            'manufacturer_id' => !empty($validated['manufacturer_id']) ? (int) $validated['manufacturer_id'] : null,
            'fieldset_id' => !empty($validated['fieldset_id']) ? (int) $validated['fieldset_id'] : null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->snipe->createRecord('models', $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return response()->json([
                'message' => 'Failed to create model.',
                'errors' => $response['messages'] ?? ['api' => ['Unknown API error.']],
            ], 422);
        }

        $createdPayload = is_array($response['payload'] ?? null)
            ? $response['payload']
            : $response;
        $modelId = (int) ($createdPayload['id'] ?? 0);

        $model = $modelId > 0
            ? $this->snipe->request('models/' . $modelId)
            : $createdPayload;

        return response()->json([
            'message' => 'Model created successfully.',
            'model' => $this->mapModelOption($model),
        ]);
    }

    public function modelDetail(int $id): JsonResponse
    {
        file_put_contents(storage_path('logs/debug_snipeit.log'), "modelDetail reached for ID: " . $id . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        $model = $this->snipe->request('models/' . $id);
        \Log::info('SnipeIT raw model response', ['id' => $id, 'data' => $model]);

        if (!is_array($model) || empty($model['id'])) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        // Always fetch from fieldsets/{id} — only that endpoint returns
        // the full field definitions including 'type' and 'field_values'
        // (list-based default_fieldset_values has db_column_name but lacks those keys).
        $fieldsetId = (int) data_get($model, 'fieldset.id', 0);
        \Log::info('SnipeIT Model FieldsetId', ['id' => $fieldsetId, 'model_id' => $id]);
        if ($fieldsetId > 0) {
            $fieldsetData = $this->snipe->request('fieldsets/' . $fieldsetId);
            \Log::info('SnipeIT FieldsetData', ['data' => $fieldsetData]);
            
            // Response shape: { "id": N, "name": "...", "fields": { "total": N, "rows": [...] } }
            $fsRows = $fieldsetData['fields']['rows'] ?? $fieldsetData['rows'] ?? [];
            if (!empty($fsRows)) {
                $model['default_fieldset_values'] = $fsRows;
            }
        }

        \Log::info('SnipeIT ModelDetail Final', ['model' => $model]);

        return response()->json(['model' => $this->mapModelOption($model)]);
    }

    public function createCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category_type' => 'required|string|in:Asset,License,Accessory,Consumable,Component',
        ]);

        $response = $this->snipe->createRecord('categories', [
            'name'          => trim($validated['name']),
            'category_type' => $validated['category_type'],
        ]);

        if (($response['status'] ?? 'error') !== 'success') {
            return response()->json([
                'message' => 'Failed to create category.',
                'errors'  => $response['messages'] ?? ['api' => ['Unknown API error.']],
            ], 422);
        }

        $created = is_array($response['payload'] ?? null) ? $response['payload'] : $response;

        return response()->json([
            'message'  => 'Category created successfully.',
            'category' => [
                'id'   => (int) ($created['id'] ?? 0),
                'name' => (string) ($created['name'] ?? ''),
            ],
        ]);
    }

    public function assetsByType(string $type): JsonResponse
    {
        $normalizedType = strtolower($type);

        if ($normalizedType === 'asset' || $normalizedType === 'hardware') {
            $normalizedType = 'assets';
        }

        if ($normalizedType === 'licenses') {
            $normalizedType = 'license';
        }

        if ($normalizedType === 'accessory') {
            $normalizedType = 'accessories';
        }

        if ($normalizedType === 'components') {
            $normalizedType = 'component';
        }

        if ($normalizedType === 'consumables') {
            $normalizedType = 'consumable';
        }

        if (!array_key_exists($normalizedType, self::ASSET_TYPE_LABELS)) {
            abort(404);
        }

        return match ($normalizedType) {
            'consumable' => response()->json($this->buildConsumables()),
            'license' => response()->json($this->buildLicenses()),
            'accessories' => response()->json($this->buildAccessories(true)),
            'component' => response()->json($this->buildComponents()),
            default => response()->json($this->buildHardwareAssets($normalizedType)),
        };
    }

    public function suppliers(): JsonResponse
    {
        return response()->json(
            collect($this->snipe->fetchRows('suppliers'))
                ->map(fn (array $s) => [
                    'id' => (int) ($s['id'] ?? 0),
                    'name' => (string) ($s['name'] ?? '-'),
                ])
                ->filter(fn (array $s) => $s['id'] > 0)
                ->values()
                ->all(),
        );
    }

    public function createSupplier(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'fax' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'url' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        $response = $this->snipe->createRecord('suppliers', array_filter($validated));

        if (($response['status'] ?? 'error') !== 'success') {
            return response()->json([
                'message' => 'Failed to create supplier.',
                'errors' => $response['messages'] ?? ['api' => ['Unknown API error.']],
            ], 422);
        }

        $created = is_array($response['payload'] ?? null) ? $response['payload'] : $response;

        return response()->json([
            'message' => 'Supplier created successfully.',
            'supplier' => [
                'id' => (int) ($created['id'] ?? 0),
                'name' => (string) ($created['name'] ?? ''),
            ],
        ]);
    }

    public function manufacturers(): JsonResponse
    {
        return response()->json(
            collect($this->snipe->fetchRows('manufacturers'))
                ->map(fn (array $m) => [
                    'id' => (int) ($m['id'] ?? 0),
                    'name' => (string) ($m['name'] ?? '-'),
                ])
                ->filter(fn (array $m) => $m['id'] > 0)
                ->values()
                ->all(),
        );
    }

    public function createManufacturer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:255',
            'support_url' => 'nullable|url|max:255',
            'support_phone' => 'nullable|string|max:255',
            'support_email' => 'nullable|email|max:255',
        ]);

        $response = $this->snipe->createRecord('manufacturers', array_filter($validated));

        if (($response['status'] ?? 'error') !== 'success') {
            return response()->json([
                'message' => 'Failed to create manufacturer.',
                'errors' => $response['messages'] ?? ['api' => ['Unknown API error.']],
            ], 422);
        }

        $created = is_array($response['payload'] ?? null) ? $response['payload'] : $response;

        return response()->json([
            'message' => 'Manufacturer created successfully.',
            'manufacturer' => [
                'id' => (int) ($created['id'] ?? 0),
                'name' => (string) ($created['name'] ?? ''),
            ],
        ]);
    }

    public function userAssets(int $id, string $type = 'assets'): JsonResponse
    {
        try {
            $endpointType = strtolower($type);
            if ($endpointType === 'license') $endpointType = 'licenses';
            if ($endpointType === 'accessories' || $endpointType === 'accessory') $endpointType = 'accessories';
            if ($endpointType === 'consumable' || $endpointType === 'consumables') $endpointType = 'consumables';
            if ($endpointType === 'component') $endpointType = 'components';
            
            $userId = $id;

            // PRE-FETCH the "best" historical metadata for each item
            // We want the latest record that actually HAS a serial or an inventory_number
            $localHistoryMap = \App\Models\StbItem::whereHas('stb', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->whereNotNull('snipeit_asset_id')
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('snipeit_asset_id')
                ->map(function($group) {
                    return [
                        'serial' => $group->first(fn($i) => !empty($i->serial_no))?->serial_no,
                        'inventory_number' => $group->first(fn($i) => !empty($i->inventory_number))?->inventory_number,
                        'computer_id' => $group->first(fn($i) => !empty($i->inventory_number))?->computer_id,
                    ];
                });

            // 1. Fetch items checked out DIRECTLY to the user
            $endpoint = "users/{$id}/" . $endpointType;
            if ($endpointType === 'assets' || $endpointType === 'hardware') {
                $endpoint = "users/{$id}/assets";
            }

            $response = $this->snipe->request($endpoint, ['limit' => 500]);
            $rows = collect(data_get($response, 'rows', []));
            // 2. DEEP FETCH: If searching for Accessories/Licenses/Components, 
            // we ALSO need to find items checked out to the user's Hardware Assets.
            if (in_array($endpointType, ['accessories', 'licenses', 'components'])) {
                // Fetch user's hardware assets first to get their IDs
                $hardwareResponse = $this->snipe->request("users/{$id}/assets", ['limit' => 100]);
                $userHardware = data_get($hardwareResponse, 'rows', []);
                $userHardwareIds = collect($userHardware)->pluck('id')->all();
                $userHardwareMap = collect($userHardware)->keyBy('id');

                if (!empty($userHardwareIds)) {
                    // We need to find accessories that are checked out to these specific asset IDs.
                    // Since Snipe-IT doesn't have a direct "accessories by asset" endpoint, 
                    // we use our own STB history as a hint to find items previously given to this user.
                    $hintItemIds = \App\Models\StbItem::whereHas('stb', function ($q) use ($userId) {
                            $q->where('user_id', $userId);
                        })
                        ->where('kategori', $type)
                        ->whereNotNull('snipeit_asset_id')
                        ->distinct()
                        ->pluck('snipeit_asset_id')
                        ->all();

                    foreach ($hintItemIds as $snipeId) {
                        // Fetch the specific item checkout status from Snipe-IT
                        $checkoutEndpoint = $endpointType . "/" . $snipeId . "/" . ($endpointType === 'components' ? 'assets' : 'checkedout');
                        $checkouts = $this->snipe->request($checkoutEndpoint, ['limit' => 50]);
                        $coRows = data_get($checkouts, 'rows', []);

                        foreach ($coRows as $co) {
                            $assignedTo = $co['assigned_to'] ?? [];
                            $assignedType = $co['type'] ?? (is_array($assignedTo) ? ($assignedTo['type'] ?? null) : null);
                            $assignedId = (int) (is_array($assignedTo) ? ($assignedTo['id'] ?? 0) : $assignedTo);

                            if ($assignedType === 'asset' && in_array($assignedId, $userHardwareIds)) {
                                // This item is currently checked out to one of the user's assets!
                                // Fetch the item details to add it to the list
                                $itemDetails = $this->snipe->request($endpointType . "/" . $snipeId);
                                if (!empty($itemDetails['id'])) {
                                    $itemDetails['_parent_computer_id'] = $assignedId;
                                    $itemDetails['_parent_computer_name'] = $userHardwareMap[$assignedId]['name'] ?? $userHardwareMap[$assignedId]['asset_tag'] ?? 'Hardware';
                                    $rows->push($itemDetails);
                                }
                            }
                        }
                    }
                }
            }


            // 3. Transform and return
            $items = $rows->unique('id')
                ->map(function ($a) use ($type, $localHistoryMap) {
                    $item = null;
                    $snipeId = (int) ($a['id'] ?? 0);
                    $localHint = $localHistoryMap->get($snipeId);

                    switch ($type) {
                        case 'license':
                            $item = [
                                'id' => (int) ($a['id'] ?? 0),
                                'name' => (string) ($a['name'] ?? '-'),
                                'serial' => (string) ($a['serial'] ?? ''),
                                'otherserial' => (string) ($a['product_key'] ?? ''),
                                'type_name' => (string) data_get($a, 'category.name', 'License'),
                                'asset_type_label' => (string) data_get($a, 'manufacturer.name', 'License'),
                                'asset_type' => 'license',
                                'remaining' => (int) ($a['free_seats_count'] ?? 1),
                                'inventory_number' => (string) ($a['product_key'] ?? ''),
                                'state_name' => 'Available',
                            ];
                            break;
                        case 'accessories':
                            $item = [
                                'id' => (int) ($a['id'] ?? 0),
                                'name' => (string) ($a['name'] ?? '-'),
                                'serial' => '',
                                'otherserial' => (string) ($a['_parent_computer_name'] ?? ''),
                                'type_name' => (string) data_get($a, 'category.name', 'Accessory'),
                                'asset_type_label' => (string) data_get($a, 'manufacturer.name', (string) ($a['model_number'] ?? 'Accessory')),
                                'asset_type' => 'accessories',
                                'remaining' => 1,
                                'inventory_number' => (string) ($a['_parent_computer_name'] ?? ''),
                                'state_name' => 'Available',
                            ];
                            break;
                        case 'consumable':
                            $item = [
                                'id' => (int) ($a['id'] ?? 0),
                                'name' => (string) ($a['name'] ?? '-'),
                                'serial' => '',
                                'otherserial' => (string) ($a['item_no'] ?? ''),
                                'type_name' => (string) data_get($a, 'category.name', 'Consumable'),
                                'asset_type_label' => (string) data_get($a, 'manufacturer.name', 'Consumable'),
                                'asset_type' => 'consumable',
                                'remaining' => (int) ($a['remaining'] ?? 1),
                                'inventory_number' => (string) ($a['item_no'] ?? ''),
                                'state_name' => 'Available',
                            ];
                            break;
                        case 'component':
                            $item = [
                                'id' => (int) ($a['id'] ?? 0),
                                'name' => (string) ($a['name'] ?? '-'),
                                'serial' => (string) ($a['serial'] ?? ''),
                                'otherserial' => (string) ($a['_parent_computer_name'] ?? ''),
                                'type_name' => (string) data_get($a, 'category.name', 'Component'),
                                'asset_type_label' => (string) data_get($a, 'manufacturer.name', 'Component'),
                                'asset_type' => 'component',
                                'remaining' => 1,
                                'inventory_number' => (string) ($a['_parent_computer_name'] ?? ''),
                                'state_name' => 'Available',
                            ];
                            break;
                        default:
                            $item = $this->transformHardwareAsset($a, $type);
                    }

                    // Priority 1: Metadata from Snipe-IT direct assignment
                    if (isset($a['_parent_computer_id'])) {
                        $item['computer_id'] = $a['_parent_computer_id'];
                        $item['inventory_number'] = $a['_parent_computer_name'];
                    } else if (in_array($type, ['accessories', 'license', 'consumable', 'component'])) {
                        // For direct-to-user checkouts, Snipe-IT doesn't return the note in the index.
                        // We fetch the specific checkouts for this item to find the note for this user.
                        try {
                            $endpoint = match($type) {
                                'accessories' => "accessories/{$snipeId}/checkedout",
                                'license'     => "licenses/{$snipeId}/checkedout",
                                'component'   => "components/{$snipeId}/assets",
                                'consumable'  => "consumables/{$snipeId}/users",
                                default       => null
                            };

                            if ($endpoint) {
                                $checkoutsRes = $this->snipe->request($endpoint, ['limit' => 50]);
                                $coRows = data_get($checkoutsRes, 'rows', []);
                                foreach ($coRows as $co) {
                                    $assignedTo = $co['assigned_to'] ?? [];
                                    $assignedId = (int) (is_array($assignedTo) ? ($assignedTo['id'] ?? 0) : $assignedTo);
                                    
                                    if ($assignedId === (int)$userId) {
                                        $note = (string)($co['note'] ?? '');
                                        if ($note !== '') {
                                            // Parse SN: ... | Ref: ...
                                            if (preg_match('/SN:\s*([^|]+)/', $note, $snMatch)) {
                                                $item['serial'] = trim($snMatch[1]);
                                            }
                                            if (preg_match('/Ref:\s*([^|]+)/', $note, $refMatch)) {
                                                $item['inventory_number'] = trim($refMatch[1]);
                                            }
                                            break;
                                        }
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning("Failed to fetch checkout note for {$type} #{$snipeId}: " . $e->getMessage());
                        }
                    }

                    // Priority 2: If SN or Asset is still empty, merge from local STB history
                    if ($localHint) {
                        if (empty($item['serial']) && !empty($localHint['serial'])) {
                            $item['serial'] = (string) $localHint['serial'];
                        }
                        if ((empty($item['inventory_number']) || $item['inventory_number'] === '-') && !empty($localHint['inventory_number'])) {
                            $item['inventory_number'] = (string) $localHint['inventory_number'];
                            $item['computer_id'] = $localHint['computer_id'];
                        }
                    }

                    return $item;
                })
                ->values();

            return response()->json($items);
        } catch (\Throwable $e) {
            \Log::error('userAssets deep fetch error: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    private function transformUser(array $user): array
    {
        $firstName = trim((string) ($user['first_name'] ?? ''));
        $lastName = trim((string) ($user['last_name'] ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);

        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => $fullName !== '' ? $fullName : (string) ($user['name'] ?? '-'),
            'username' => (string) ($user['username'] ?? $user['email'] ?? ''),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => (string) ($user['email'] ?? ''),
            'phone' => (string) ($user['phone'] ?? $user['mobile'] ?? ''),
            'jobtitle' => (string) ($user['jobtitle'] ?? ''),
            'title_name' => (string) data_get($user, 'title.name', ''),
            'manager_id' => $this->nestedId($user, 'manager'),
            'manager_name' => $this->nestedName($user, 'manager'),
            'location_id' => $this->nestedId($user, 'location'),
            'location_name' => $this->nestedName($user, 'location'),
            'department_id' => $this->nestedId($user, 'department'),
            'department_name' => $this->nestedName($user, 'department'),
            'company_id' => $this->nestedId($user, 'company'),
            'company_name' => $this->nestedName($user, 'company'),
        ];
    }

    private function buildHardwareAssets(string $type): array
    {
        return collect($this->snipe->fetchRows('hardware'))
            ->filter(fn (array $asset) => $this->hardwareMatchesType($asset, $type))
            ->map(fn (array $asset) => $this->transformHardwareAsset($asset, $type))
            ->values()
            ->all();
    }

    private function buildConsumables(): array
    {
        return collect($this->snipe->fetchRows('consumables'))
            ->map(function (array $asset) {
                $stock = (int) ($asset['qty'] ?? 0);
                $remaining = (int) ($asset['remaining'] ?? $asset['remaining_qty'] ?? 0);
                $used = max(0, $stock - $remaining);

                return [
                    'id' => (int) ($asset['id'] ?? 0),
                    'name' => (string) ($asset['name'] ?? '-'),
                    'serial' => '',
                    'otherserial' => (string) ($asset['item_no'] ?? ''),
                    'state_name' => 'Available',
                    'group_name' => $this->nestedName($asset, 'location'),
                    'type_name' => $this->nestedName($asset, 'category'),
                    'stock' => $stock,
                    'remaining' => $remaining,
                    'used' => $used,
                    'asset_type' => 'consumable',
                    'asset_type_label' => self::ASSET_TYPE_LABELS['consumable'],
                    'users_id' => null,
                    'location_name' => $this->nestedName($asset, 'location'),
                ];
            })
            ->filter(fn (array $asset) => $asset['id'] > 0)
            ->values()
            ->all();
    }

    private function buildLicenses(): array
    {
        return collect($this->snipe->fetchRows('licenses'))
            ->map(function (array $asset) {
                $totalSeats = (int) ($asset['seats'] ?? 0);
                $freeSeats = (int) ($asset['free_seats_count'] ?? $asset['free_seats'] ?? 0);
                $usedSeats = max(0, $totalSeats - $freeSeats);

                return [
                    'id' => (int) ($asset['id'] ?? 0),
                    'name' => (string) ($asset['name'] ?? '-'),
                    'serial' => (string) ($asset['serial'] ?? ''),
                    'otherserial' => (string) ($asset['product_key'] ?? ''),
                    'state_name' => 'Available',
                    'group_name' => $this->nestedName($asset, 'location'),
                    'type_name' => (string) data_get($asset, 'manufacturer.name', 'License'),
                    'stock' => $totalSeats,
                    'used' => $usedSeats,
                    'asset_type' => 'license',
                    'asset_type_label' => self::ASSET_TYPE_LABELS['license'],
                    'remaining' => $freeSeats,
                    'users_id' => null,
                    'location_name' => '-',
                ];
            })
            ->filter(fn (array $asset) => $asset['id'] > 0 && ($asset['stock'] - $asset['used']) > 0)
            ->values()
            ->all();
    }

    private function buildAccessories(bool $forceRefresh = false): array
    {
        return collect($this->snipe->fetchRows('accessories', [], 500, $forceRefresh))
            ->map(function (array $asset) {
                $qty = (int) ($asset['qty'] ?? 0);
                $remaining = (int) ($asset['remaining_qty'] ?? $asset['remaining'] ?? 0);
                $used = max(0, $qty - $remaining);

                return [
                    'id' => (int) ($asset['id'] ?? 0),
                    'name' => (string) ($asset['name'] ?? '-'),
                    'serial' => '',
                    'otherserial' => (string) ($asset['model_number'] ?? ''),
                    'state_name' => 'Available',
                    'group_name' => $this->nestedName($asset, 'location'),
                    'type_name' => $this->nestedName($asset, 'category'),
                    'stock' => $qty,
                    'remaining' => $remaining,
                    'used' => $used,
                    'asset_type' => 'accessories',
                    'asset_type_label' => self::ASSET_TYPE_LABELS['accessories'],
                    'remaining' => $remaining,
                    'users_id' => null,
                    'location_name' => $this->nestedName($asset, 'location'),
                ];
            })
            ->filter(fn (array $asset) => $asset['id'] > 0)
            ->values()
            ->all();
    }

    private function buildComponents(): array
    {
        return collect($this->snipe->fetchRows('components'))
            ->map(function (array $asset) {
                $qty = (int) ($asset['qty'] ?? 0);
                $remaining = (int) ($asset['remaining_qty'] ?? $asset['remaining'] ?? 0);
                $used = max(0, $qty - $remaining);

                return [
                    'id' => (int) ($asset['id'] ?? 0),
                    'name' => (string) ($asset['name'] ?? '-'),
                    'serial' => '',
                    'otherserial' => (string) ($asset['serial'] ?? ''),
                    'state_name' => 'Available',
                    'group_name' => $this->nestedName($asset, 'location'),
                    'type_name' => $this->nestedName($asset, 'category'),
                    'stock' => $qty,
                    'remaining' => $remaining,
                    'used' => $used,
                    'asset_type' => 'component',
                    'asset_type_label' => self::ASSET_TYPE_LABELS['component'],
                    'remaining' => $remaining,
                    'users_id' => null,
                    'location_name' => $this->nestedName($asset, 'location'),
                ];
            })
            ->filter(fn (array $asset) => $asset['id'] > 0)
            ->values()
            ->all();
    }

    private function transformHardwareAsset(array $asset, string $type): array
    {
        $status = $asset['status_label'] ?? [];
        $assignedTo = $asset['assigned_to'] ?? [];

        return [
            'id' => (int) ($asset['id'] ?? 0),
            'name' => (string) ($asset['name'] ?? $asset['asset_tag'] ?? '-'),
            'serial' => (string) ($asset['serial'] ?? ''),
            'otherserial' => (string) ($asset['asset_tag'] ?? ''),
            'state' => (int) ($status['id'] ?? 0),
            'state_name' => (string) ($status['name'] ?? 'Unknown'),
            'group_name' => $this->nestedName($asset, 'location'),
            'type_name' => $this->extractHardwareTypeName($asset),
            'stock' => '-',
            'used' => '-',
            'asset_type' => $type,
            'asset_type_label' => (string) data_get($asset, 'model.name', 'Asset'),
            'users_id' => (int) ($assignedTo['id'] ?? 0) ?: null,
            'location_name' => $this->nestedName($asset, 'location'),
        ];
    }

    private function hardwareMatchesType(array $asset, string $type): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($asset['name'] ?? ''),
            (string) ($asset['asset_tag'] ?? ''),
            (string) ($asset['serial'] ?? ''),
            (string) data_get($asset, 'model.name', ''),
            (string) data_get($asset, 'model.model_number', ''),
            (string) data_get($asset, 'category.name', ''),
            (string) data_get($asset, 'manufacturer.name', ''),
        ])));

        return match ($type) {
            'assets' => true,
            'asset' => true,
            'computer' => str_contains($haystack, 'computer')
                || str_contains($haystack, 'desktop')
                || str_contains($haystack, 'laptop')
                || str_contains($haystack, 'pc'),
            'monitor' => str_contains($haystack, 'monitor')
                || str_contains($haystack, 'display'),
            'network' => str_contains($haystack, 'network')
                || str_contains($haystack, 'switch')
                || str_contains($haystack, 'router')
                || str_contains($haystack, 'access point')
                || str_contains($haystack, 'firewall'),
            'cctv' => str_contains($haystack, 'cctv')
                || str_contains($haystack, 'camera'),
            'nvr' => str_contains($haystack, 'nvr')
                || str_contains($haystack, 'dvr'),
            default => true,
        };
    }

    private function extractHardwareTypeName(array $asset): string
    {
        return (string) data_get($asset, 'category.name')
            ?: (string) data_get($asset, 'model.name')
            ?: 'Hardware';
    }

    private function mapModelOption(array $model): array
    {
        return [
            'id' => (int) ($model['id'] ?? 0),
            'name' => (string) ($model['name'] ?? '-'),
            'label' => trim(implode(' - ', array_filter([
                (string) ($model['name'] ?? ''),
                (string) data_get($model, 'manufacturer.name', ''),
                (string) data_get($model, 'category.name', ''),
            ]))),
            'manufacturer_name' => (string) data_get($model, 'manufacturer.name', '-'),
            'category_id' => (int) data_get($model, 'category.id', 0),
            'category_name' => (string) data_get($model, 'category.name', '-'),
            'fieldset_name' => (string) data_get($model, 'fieldset.name', ''),
            'require_serial' => (bool) ($model['require_serial'] ?? false),
            'default_fields' => $this->mapFieldDefinitions($model['default_fieldset_values'] ?? []),
            'has_details'    => true,
        ];
    }

    /**
     * Normalise Snipe-IT field definitions to a consistent shape.
     *
     * Snipe-IT may return `default_fieldset_values` as:
     *  a) Indexed array where every element has `db_column_name`, `name`, etc.
     *  b) Associative object keyed by column name whose values only hold `value`/`field_id`.
     *
     * For (b) the fields are useless without names; callers should first replace the raw
     * values with a proper fieldset fetch before calling this method.
     *
     * Key-name variants handled:
     *   format / field_format   →  format
     *   default_value / value   →  default_value
     */
    private function mapFieldDefinitions(array $rawFields): array
    {
        \Log::info('SnipeIT rawFields in mapFieldDefinitions', ['count' => count($rawFields), 'first' => count($rawFields) > 0 ? $rawFields[0] : null]);
        return collect($rawFields)
            ->filter(fn ($field) => !empty(data_get($field, 'db_column_name')) || !empty(data_get($field, 'db_column')))
            ->map(fn ($field) => [
                'name'           => (string) (data_get($field, 'name', '-')),
                'db_column_name' => (string) (data_get($field, 'db_column_name', data_get($field, 'db_column', ''))),
                'default_value'  => data_get($field, 'default_value', data_get($field, 'value')),
                'format'         => (string) (data_get($field, 'format', data_get($field, 'field_format', 'ANY'))),
                'type'           => (string) (data_get($field, 'type', data_get($field, 'element', 'text'))),
                'field_values'   => (string) (data_get($field, 'field_values', '')),
                'required'       => (bool) (data_get($field, 'required', false)),
            ])
            ->values()
            ->all();
    }

    private function nestedId(array $payload, string $key): ?int
    {
        $value = data_get($payload, $key . '.id');

        return is_numeric($value) ? (int) $value : null;
    }

    private function nestedName(array $payload, string $key): string
    {
        return (string) data_get($payload, $key . '.name', '-');
    }
}