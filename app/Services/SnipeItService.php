<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SnipeItService
{
    private function client(bool $impersonate = true)
    {
        $client = Http::withToken((string) config('services.snipeit.token'))
            ->acceptJson()
            ->connectTimeout((int) config('services.snipeit.connect_timeout', 3))
            ->timeout((int) config('services.snipeit.timeout', 10));

        // Use impersonation if requested and a logged-in user has a Snipe-IT User ID
        if ($impersonate) {
            $snipeUserId = auth()->user()?->snipeit_user_id;
            if ($snipeUserId) {
                $client->withHeaders([
                    'x-impersonate-user' => (string) $snipeUserId
                ]);
            }
        }

        return $client;
    }

    public function request(string $endpoint, array $query = [], bool $forceRefresh = false): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [];
        }

        $cacheKey = 'snipeit_api:' . md5($endpoint . serialize($query));

        if ($forceRefresh) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }

        $ttl = $this->cacheTtlForEndpoint($endpoint, $query);

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, now()->addSeconds($ttl), function () use ($url, $endpoint, $query) {
            $response = $this->client(false)->get($url . '/api/v1/' . ltrim($endpoint, '/'), $query);
            return $this->decodeResponse($response);
        });
    }

    /**
     * Fire multiple GET requests to Snipe-IT in parallel (HTTP Pool).
     *
     * @param  array<string, array{0: string, 1: array}>  $requests
     *         Associative array of  key => [endpoint, query_params]
     *         e.g. ['assets' => ['users/5/assets', []], 'licenses' => ['users/5/licenses', []]]
     * @return array<string, array>  Keyed results matching the input keys.
     */
    public function requestPool(array $requests, bool $forceRefresh = false): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '' || empty($requests)) {
            return array_fill_keys(array_keys($requests), []);
        }

        $token   = (string) config('services.snipeit.token');
        $timeout = (int) config('services.snipeit.timeout', 15);

        // Check cache first — only pool the ones that are not yet cached
        $results   = [];
        $toFetch   = [];

        foreach ($requests as $key => [$endpoint, $query]) {
            $cacheKey = 'snipeit_api:' . md5($endpoint . serialize($query));

            if ($forceRefresh) {
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
            }

            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($cached !== null) {
                $results[$key] = $cached;
            } else {
                $toFetch[$key] = [$endpoint, $query, $cacheKey];
            }
        }

        if (empty($toFetch)) {
            return $results;
        }

        // Build key list in stable order for pool callback indexing
        $keys = array_keys($toFetch);

        try {
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($url, $token, $timeout, $toFetch, $keys) {
                foreach ($keys as $key) {
                    [$endpoint, $query] = $toFetch[$key];
                    $pool->as($key)
                        ->withToken($token)
                        ->acceptJson()
                        ->timeout($timeout)
                        ->get($url . '/api/v1/' . ltrim($endpoint, '/'), $query);
                }
            });

            foreach ($keys as $key) {
                [$endpoint, $query, $cacheKey] = $toFetch[$key];
                $response = $responses[$key] ?? null;

                if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                    $decoded = $response->json();
                    $decoded = is_array($decoded) ? $decoded : [];
                } else {
                    $decoded = [];
                }

                $ttl = $this->cacheTtlForEndpoint($endpoint, $query);
                \Illuminate\Support\Facades\Cache::put($cacheKey, $decoded, now()->addSeconds($ttl));
                $results[$key] = $decoded;
            }
        } catch (\Throwable $e) {
            Log::error('SnipeItService::requestPool error: ' . $e->getMessage());
            // Fall back: return empty arrays for keys that failed
            foreach ($keys as $key) {
                $results[$key] = $results[$key] ?? [];
            }
        }

        return $results;
    }

    public function createRecord(string $endpoint, array $payload = []): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        $response = $this->client(true)->post($url . '/api/v1/' . ltrim($endpoint, '/'), $payload);

        return $this->decodeAnyResponse($response);
    }

    public function deleteRecord(string $endpoint, int $id): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        $response = $this->client()->delete($url . '/api/v1/' . ltrim($endpoint, '/') . '/' . $id);

        return $this->decodeAnyResponse($response);
    }

    public function updateRecord(string $endpoint, int $id, array $payload = []): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        $response = $this->client()->put($url . '/api/v1/' . ltrim($endpoint, '/') . '/' . $id, $payload);

        return $this->decodeAnyResponse($response);
    }

    public function checkoutAsset(string $resource, int $id, array $payload = []): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        // Snipe-IT checkout endpoints follow the pattern: /resource/{id}/checkout
        $endpoint = sprintf('%s/api/v1/%s/%d/checkout', $url, ltrim($resource, '/'), $id);

        Log::debug('Snipe-IT Checkout Request', [
            'url'     => $endpoint,
            'payload' => $payload
        ]);

        $response = $this->client()
            ->asJson()
            ->post($endpoint, $payload);

        Log::debug('Snipe-IT Checkout Response', [
            'status' => $response->status(),
            'body'   => $response->json()
        ]);

        return $this->decodeAnyResponse($response);
    }

    public function adjustQuantity(string $resource, int $id, int $amount, string $note): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        $endpoint = sprintf('%s/api/v1/%s/%d/adjust-quantity', $url, ltrim($resource, '/'), $id);
        $payload = [
            'amount' => $amount,
            'note' => $note,
        ];
        $client = $this->client()->asJson();
        $response = $client->post($endpoint, $payload);

        if ($response->status() === 405) {
            $response = $client->put($endpoint, $payload);
        }

        if ($response->status() === 405) {
            $response = $client->patch($endpoint, $payload);
        }

        if ($response->status() === 405) {
            $component = $this->request("{$resource}/{$id}", [], true);
            $currentQuantity = (int) ($component['qty'] ?? $component['remaining'] ?? 0);
            $newQuantity = $currentQuantity + $amount;

            if ($newQuantity < 0) {
                return [
                    'status' => 'error',
                    'messages' => ['qty' => ['Insufficient quantity available.']],
                ];
            }

            $response = $client->put(
                sprintf('%s/api/v1/%s/%d', $url, ltrim($resource, '/'), $id),
                [
                    'qty' => $newQuantity,
                    'notes' => $note,
                ],
            );
        }

        return $this->decodeAnyResponse($response);
    }

    public function getLicenseSeats(int $id, bool $forceRefresh = false): array
    {
        $payload = $this->request("licenses/{$id}/seats", [], $forceRefresh);
        return $payload['rows'] ?? [];
    }

    public function getAccessoryCheckouts(int $id, bool $forceRefresh = false): array
    {
        $payload = $this->request("accessories/{$id}/checkedout", [], $forceRefresh);
        return $payload['rows'] ?? [];
    }

    public function getComponentCheckouts(int $id, bool $forceRefresh = false): array
    {
        $payload = $this->request("components/{$id}/assets", [], $forceRefresh);
        return $payload['rows'] ?? [];
    }

    public function checkoutLicenseSeat(int $licenseId, int $seatId, array $payload = []): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        // License checkout endpoint: /licenses/{id}/seats/{seat_id}
        $endpoint = sprintf('%s/api/v1/licenses/%d/seats/%d', $url, $licenseId, $seatId);

        Log::debug('Snipe-IT License Seat Checkout Request', [
            'url'     => $endpoint,
            'payload' => $payload
        ]);

        $response = $this->client()
            ->asJson()
            ->put($endpoint, $payload);

        Log::debug('Snipe-IT License Seat Checkout Response', [
            'status' => $response->status(),
            'body'   => $response->json()
        ]);

        return $this->decodeAnyResponse($response);
    }

    public function checkinLicenseSeat(int $licenseId, int $seatId, array $payload = []): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        // License checkin endpoint: /licenses/{id}/seats/{seat_id}
        // Note: For checkin, we send assigned_to = null and asset_id = null
        $endpoint = sprintf('%s/api/v1/licenses/%d/seats/%d', $url, $licenseId, $seatId);

        Log::debug('Snipe-IT License Seat Checkin Request', [
            'url'     => $endpoint,
            'payload' => $payload
        ]);

        $response = $this->client()
            ->asJson()
            ->patch($endpoint, array_merge([
                'assigned_to' => null,
                'asset_id'    => null,
            ], $payload));

        Log::debug('Snipe-IT License Seat Checkin Response', [
            'status' => $response->status(),
            'body'   => $response->json()
        ]);

        return $this->decodeAnyResponse($response);
    }

    public function checkinAsset(string $resource, int $id, array $payload = []): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status' => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        // Snipe-IT checkin endpoints follow the pattern: /resource/{id}/checkin
        $endpoint = sprintf('%s/api/v1/%s/%d/checkin', $url, ltrim($resource, '/'), $id);

        $response = $this->client()
            ->asJson()
            ->post($endpoint, $payload);

        return $this->decodeAnyResponse($response);
    }

    public function fetchRows(string $endpoint, array $query = [], int $limit = 500, bool $forceRefresh = false): array
    {
        $payload = $this->request($endpoint, array_merge([
            'limit' => $limit,
            'offset' => 0,
        ], $query), $forceRefresh);

        $rows = $payload['rows'] ?? [];

        return is_array($rows) ? $rows : [];
    }

    public function getUser(?int $id): ?array
    {
        return $this->fetchRecord('users', $id);
    }

    public function getLocation(?int $id): ?array
    {
        return $this->fetchRecord('locations', $id);
    }

    public function getHardware(?int $id): ?array
    {
        return $this->fetchRecord('hardware', $id);
    }

    public function getHardwareBySerial(string $serial): ?array
    {
        $payload = $this->request('hardware', ['serial' => $serial, 'limit' => 1]);
        return is_array($payload) ? $payload : null;
    }

    public function getHardwareByAssetTag(string $assetTag): ?array
    {
        $payload = $this->request('hardware', ['asset_tag' => $assetTag, 'limit' => 1]);
        return is_array($payload) ? $payload : null;
    }

    public function getSuppliers(): array
    {
        $payload = $this->request('suppliers', ['limit' => 500]);
        return $payload['rows'] ?? [];
    }

    public function getConsumable(?int $id): ?array
    {
        return $this->fetchRecord('consumables', $id);
    }

    public function getLicense(?int $id): ?array
    {
        return $this->fetchRecord('licenses', $id);
    }

    public function getAccessory(?int $id): ?array
    {
        return $this->fetchRecord('accessories', $id);
    }

    public function getComponent(?int $id): ?array
    {
        return $this->fetchRecord('components', $id);
    }

    private function fetchRecord(string $resource, ?int $id): ?array
    {
        if (!$id) {
            return null;
        }

        $payload = $this->request($resource . '/' . $id);

        return is_array($payload) ? $payload : null;
    }

    /**
     * Upload a file to a Snipe-IT resource's files endpoint.
     *
     * @param  string  $resource  e.g. 'hardware', 'consumables', 'components', 'accessories', 'licenses'
     * @param  int     $id        Snipe-IT item ID
     * @param  string  $content   Raw file contents
     * @param  string  $filename  Original filename (e.g. 'po-2026.pdf')
     * @param  string|null $notes Optional note attached to the file
     */
    public function uploadFile(string $resource, int $id, string $content, string $filename, ?string $notes = null): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status'   => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        $endpoint = sprintf('%s/api/v1/%s/%d/files', $url, ltrim($resource, '/'), $id);

        $pending = $this->client(true)
            ->timeout((int) config('services.snipeit.timeout', 30))
            ->attach('file[]', $content, $filename);

        $response = $pending->post($endpoint, [
            'name'  => $filename,
            'notes' => $notes ?? ''
        ]);

        return $this->decodeAnyResponse($response);
    }

    public function deleteFile(string $resource, int $resourceId, int $fileId): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status'   => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        // Snipe-IT file delete: DELETE /resource/{id}/files/{file_id}
        $endpoint = sprintf('%s/api/v1/%s/%d/files/%d', $url, ltrim($resource, '/'), $resourceId, $fileId);

        $response = $this->client()->delete($endpoint);

        return $this->decodeAnyResponse($response);
    }

    public function updateAvatar(int $userId, string $content, string $filename): array
    {
        $url = rtrim((string) config('services.snipeit.url'), '/');

        if ($url === '') {
            return [
                'status'   => 'error',
                'messages' => ['api' => ['Snipe-IT URL is not configured.']],
            ];
        }

        $endpoint = sprintf('%s/api/v1/users/%d', $url, $userId);

        // Snipe-IT user avatar upload typically uses multipart POST or PATCH with 'avatar' field.
        // We often use POST with _method=PATCH for better compatibility.
        $response = $this->client()
            ->timeout((int) config('services.snipeit.timeout', 20))
            ->attach('avatar', $content, $filename)
            ->post($endpoint, [
                '_method' => 'PATCH'
            ]);

        return $this->decodeAnyResponse($response);
    }

    /**
     * Flush all Snipe-IT API cache entries for a given User (by snipeit_user_id).
     * Call this after any mutation on that user so the next page load is fresh.
     */
    public function flushCacheForUser(int $snipeId): void
    {
        $endpoints = [
            ["users/{$snipeId}", []],
            ["users/{$snipeId}/assets", []],
            ["users/{$snipeId}/licenses", []],
            ["users/{$snipeId}/accessories", []],
            ["users/{$snipeId}/files", []],
            ["users/{$snipeId}/eulas", []],
            // Flush all parallel history pages
            ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'limit' => 500, 'offset' => 0]],
            ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'limit' => 500, 'offset' => 500]],
            ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'limit' => 500, 'offset' => 1000]],
            // Flush all parallel consumable log pages
            ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'action_type' => 'checkout', 'limit' => 500, 'offset' => 0]],
            ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'action_type' => 'checkout', 'limit' => 500, 'offset' => 500]],
            ['reports/activity', ['target_type' => 'user', 'target_id' => $snipeId, 'action_type' => 'checkout', 'limit' => 500, 'offset' => 1000]],
            ['users', ['manager_id' => $snipeId, 'limit' => 200]],
            ['locations', ['manager_id' => $snipeId, 'limit' => 200]],
        ];

        foreach ($endpoints as [$endpoint, $query]) {
            $cacheKey = 'snipeit_api:' . md5($endpoint . serialize($query));
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }
    }

    /**
     * Flush all Snipe-IT API cache entries for a given Asset (by type and ID).
     * Call this after any mutation on that asset so the next page load is fresh.
     *
     * @param string $type  Normalised type: 'assets', 'license', 'accessories', 'consumable', 'component'
     */
    public function flushCacheForAsset(string $type, int $assetId): void
    {
        $endpoint = match ($type) {
            'assets'      => 'hardware',
            'license'     => 'licenses',
            'accessories' => 'accessories',
            'consumable'  => 'consumables',
            'component'   => 'components',
            default       => 'hardware',
        };

        $defaultListQuery = ['limit' => 500, 'offset' => 0];

        $endpoints = [
            [$endpoint, $defaultListQuery],
            ["{$endpoint}/{$assetId}", []],
            ["{$endpoint}/{$assetId}/files", []],
        ];

        // Also flush checkout/activity endpoints specific to each type
        $activityEndpoints = [
            ['accessories'  => ["accessories/{$assetId}/checkedout", []]],
            ['component'    => ["components/{$assetId}/assets", []]],
            ['license'      => ["licenses/{$assetId}/seats", []]],
            ['consumable'   => ["consumables/{$assetId}/users", ['limit' => 1500]]],
            // Flush all parallel asset history pages
            ['all' => ['reports/activity', ['item_type' => $endpoint, 'item_id' => $assetId, 'limit' => 500, 'offset' => 0]]],
            ['all' => ['reports/activity', ['item_type' => $endpoint, 'item_id' => $assetId, 'limit' => 500, 'offset' => 500]]],
            ['all' => ['reports/activity', ['item_type' => $endpoint, 'item_id' => $assetId, 'limit' => 500, 'offset' => 1000]]],
        ];

        foreach ($activityEndpoints as $typeMap) {
            if (isset($typeMap[$type])) {
                $endpoints[] = $typeMap[$type];
            }
        }

        foreach ($endpoints as [$ep, $query]) {
            $cacheKey = 'snipeit_api:' . md5($ep . serialize($query));
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }
    }

    private function cacheTtlForEndpoint(string $endpoint, array $query = []): int
    {
        $isListEndpoint = str_contains($endpoint, 'reports/activity') === false && !preg_match('#/(\d+)$#', $endpoint);

        if ($isListEndpoint) {
            return 30;
        }

        return 180;
    }

    private function decodeResponse(Response $response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function decodeAnyResponse(Response $response): array
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return [
            'status' => $response->successful() ? 'success' : 'error',
            'messages' => ['api' => [$response->body()]],
        ];
    }
}