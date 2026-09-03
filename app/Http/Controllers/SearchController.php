<?php

namespace App\Http\Controllers;

use App\Models\Stb;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Inspection;
use App\Services\SnipeItService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    public function __construct(private readonly SnipeItService $snipe)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        Log::info('Search endpoint hit', ['query' => $query]);

        if (strlen($query) < 2) {
            Log::info('Query too short', ['length' => strlen($query)]);
            return response()->json(['results' => []]);
        }

        $results = collect();

        // 1. Search Local Users
        try {
            $users = User::where('name', 'like', "%{$query}%")
                ->orWhere('username', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('employee_num', 'like', "%{$query}%")
                ->limit(5)
                ->get()
                ->map(fn($u) => [
                    'id'       => $u->id,
                    'title'    => $u->name,
                    'subtitle' => ($u->department ?? '-') . ' · ' . ($u->email ?? '-'),
                    'type'     => 'user',
                    'href'     => route('users.show', $u->id),
                    'icon'     => 'User',
                ]);
            $results = $results->concat($users);
        } catch (\Throwable $e) {
            Log::warning('Search: user query failed', ['error' => $e->getMessage()]);
        }

        // 2. Search Local STB and Peminjaman docs
        try {
            $stbs = Stb::where('user_name', 'like', "%{$query}%")
                ->orWhere('req_doc_no', 'like', "%{$query}%")
                ->orWhere('po_doc_no', 'like', "%{$query}%")
                ->orWhere('location_name', 'like', "%{$query}%")
                ->orWhere('user_dept', 'like', "%{$query}%")
                ->orWhere('remark', 'like', "%{$query}%")
                ->limit(5)
                ->get()
                ->map(fn($s) => [
                    'id'       => $s->id,
                    'title'    => $this->formatStbDocId($s) . ' · ' . ($s->user_name ?: '-'),
                    'subtitle' => ($s->document_type === 'loan' ? 'Peminjaman' : 'STB') . ' | ' . ($s->movement_type === 'return' ? 'Pengembalian' : 'Penyerahan'),
                    'type'     => $s->document_type === 'loan' ? 'peminjaman' : 'stb',
                    'href'     => route($s->document_type === 'handover' ? 'stb.show' : 'peminjaman.show', $s->id),
                    'icon'     => 'FileText',
                ]);
            $results = $results->concat($stbs);
        } catch (\Throwable $e) {
            Log::warning('Search: STB/peminjaman query failed', ['error' => $e->getMessage()]);
        }

        // 3. Search Inspections
        try {
            $inspections = Inspection::where('report_id', 'like', "%{$query}%")
                ->orWhere('user', 'like', "%{$query}%")
                ->orWhere('asset_tag', 'like', "%{$query}%")
                ->orWhere('device_name', 'like', "%{$query}%")
                ->orWhere('serial_number', 'like', "%{$query}%")
                ->orWhere('issue_description', 'like', "%{$query}%")
                ->limit(4)
                ->get()
                ->map(fn($ins) => [
                    'id'       => $ins->id,
                    'title'    => $ins->report_id,
                    'subtitle' => 'Inspection · ' . ($ins->user ?: $ins->device_name ?: '-'),
                    'type'     => 'inspection',
                    'href'     => route('inspection.show', $ins->id),
                    'icon'     => 'ClipboardCheck',
                ]);
            $results = $results->concat($inspections);
        } catch (\Throwable $e) {
            Log::warning('Search: inspection query failed', ['error' => $e->getMessage()]);
        }

        // 4. Search Tickets (Helpdesk)
        try {
            $tickets = Ticket::where('requester', 'like', "%{$query}%")
                ->orWhere('issue_description', 'like', "%{$query}%")
                ->orWhere('id', 'like', "%{$query}%")
                ->limit(4)
                ->get()
                ->map(fn($t) => [
                    'id'       => $t->id,
                    'title'    => "Ticket #{$t->id} · {$t->requester}",
                    'subtitle' => ($t->category ?? '-') . ' | ' . ($t->status ?? '-'),
                    'type'     => 'ticket',
                    'href'     => route('helpdesk.show', $t->id),
                    'icon'     => 'HelpCircle',
                ]);
            $results = $results->concat($tickets);
        } catch (\Throwable $e) {
            Log::warning('Search: ticket query failed', ['error' => $e->getMessage()]);
        }

        // 5. Search Snipe-IT assets in parallel
        try {
            $assetTypes = [
                'hardware'    => ['type' => 'asset',      'label' => 'Hardware',   'icon' => 'Laptop',  'routeType' => 'assets'],
                'accessories' => ['type' => 'accessory',  'label' => 'Accessory',  'icon' => 'Package', 'routeType' => 'accessories'],
                'components'  => ['type' => 'component',  'label' => 'Component',  'icon' => 'Package', 'routeType' => 'component'],
                'consumables' => ['type' => 'consumable', 'label' => 'Consumable', 'icon' => 'Package', 'routeType' => 'consumable'],
                'licenses'    => ['type' => 'license',    'label' => 'License',    'icon' => 'FileKey', 'routeType' => 'license'],
            ];

            // Build parallel request pool
            $poolRequests = [];
            foreach ($assetTypes as $endpoint => $meta) {
                $poolRequests[$endpoint] = [$endpoint, ['search' => $query, 'limit' => 5]];
            }

            $poolResults = $this->snipe->requestPool($poolRequests);

            $assets = collect($assetTypes)->flatMap(function (array $meta, string $endpoint) use ($poolResults) {
                $rows = $poolResults[$endpoint]['rows'] ?? $poolResults[$endpoint] ?? [];
                if (!is_array($rows)) {
                    return [];
                }
                return collect($rows)->map(fn(array $asset) => [
                    'id'       => $asset['id'],
                    'title'    => $asset['name'] ?? $asset['license_name'] ?? ($asset['model']['name'] ?? $meta['label']),
                    'subtitle' => $meta['label'] . ' · ' . ($asset['asset_tag'] ?? $asset['serial'] ?? $asset['product_key'] ?? '-'),
                    'type'     => $meta['type'],
                    'href'     => route('asset.show', ['assetId' => $asset['id'], 'type' => $meta['routeType']]),
                    'icon'     => $meta['icon'],
                ]);
            })->take(15);

            $results = $results->concat($assets);
        } catch (\Throwable $e) {
            Log::warning('Search: Snipe-IT asset search failed', ['error' => $e->getMessage()]);
        }

        return response()->json(['results' => $results->values()]);
    }

    private function formatStbDocId(Stb $stb): string
    {
        $location = trim((string) ($stb->location_name ?? ''));
        $locationCode = $location !== '' && $location !== '-'
            ? strtoupper(substr((string) explode(' ', $location)[0], 0, 3))
            : '';
        $dateCode  = $stb->created_at?->format('ym');
        $sequence  = sprintf('%04d', $stb->id);

        return 'STB-' . implode('-', array_filter([$locationCode, $dateCode, $sequence]));
    }
}
