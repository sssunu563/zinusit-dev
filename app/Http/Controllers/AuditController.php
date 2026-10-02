<?php

namespace App\Http\Controllers;

use App\Models\AuditItem;
use App\Models\AuditSession;
use App\Models\ActionLog;
use App\Models\User;
use App\Services\SnipeItService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function __construct(
        private readonly SnipeItService $snipe
    ) {}

    public function index()
    {
        return Inertia::render('Audit/Index', [
            'sessions' => AuditSession::with('creator:id,name')
                ->withCount('items')
                ->latest()
                ->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
        ]);

        $assets = $this->fetchAuditAssets();

        if ($assets->isEmpty()) {
            return back()->withErrors([
                'name' => 'Daftar aset aktif tidak tersedia dari Snipe-IT. Sesi audit belum dibuat.',
            ]);
        }

        $session = DB::transaction(function () use ($validated, $request, $assets): AuditSession {
            $session = AuditSession::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
                'status' => 'Open',
                'created_by' => $request->user()->id,
            ]);

            $session->items()->createMany($assets->map(fn (array $asset) => [
                'snipeit_asset_id' => (int) $asset['id'],
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'serial' => (string) ($asset['serial'] ?? ''),
                'asset_name' => (string) ($asset['name'] ?? data_get($asset, 'model.name', 'Hardware Asset')),
                'status' => 'Missing',
                'expected_location' => (string) (data_get($asset, 'location.name') ?? ''),
                'expected_department' => $this->resolveAssetDepartment($asset),
                'expected_user' => (string) (data_get($asset, 'assigned_to.name') ?? 'Available'),
            ])->all());

            return $session;
        });

        return redirect()->route('audit.show', $session->id);
    }

    public function show(AuditSession $session)
    {
        if ($session->status === 'Open' && !$session->items()->exists()) {
            $this->seedSessionItems($session);
            $session->refresh();
        }

        // Load relationships and order items by latest
        $session->load([
            'creator:id,name,email',
            'items' => function ($query) {
                $query->with('verifier:id,name')->latest('verified_at');
            }
        ]);
        
        return Inertia::render('Audit/Show', [
            'session' => $session,
        ]);
    }

    public function destroy(AuditSession $session)
    {
        abort_unless($session->status === 'Open', 422, 'Hanya sesi audit yang masih Open yang dapat dibatalkan.');

        if ($session->items()->whereNotNull('verified_at')->exists()) {
            abort(422, 'Sesi audit tidak dapat dibatalkan karena sudah memiliki aktivitas.');
        }

        $session->delete();

        return redirect()->route('audit.index')->with('success', 'Sesi audit berhasil dibatalkan.');
    }

    public function scan(Request $request, AuditSession $session)
    {
        $request->validate([
            'search' => 'required|string', // asset tag, serial, or QR URL
        ]);

        $query = trim($request->input('search'));

        \Log::info('🔍 Audit Scan Request', [
            'session_id' => $session->id,
            'raw_query' => $query,
        ]);

        // Handle URL scans like http://domain/a/{ref} or /a/{ref}
        // Also handle domain variations (localhost, 127.0.0.1, production domain, etc)
        if (preg_match('|/a/([^/?#\s]+)|i', $query, $matches)) {
            $query = urldecode($matches[1]);
            \Log::info('📍 Extracted from path', ['extracted' => $query]);
        }
        // Also handle query parameter format: ?tag=xxx
        elseif (preg_match('|[?&]tag=([^&#\s]+)|i', $query, $matches)) {
            $query = urldecode($matches[1]);
            \Log::info('📍 Extracted from query param', ['extracted' => $query]);
        }
        
        // Search in Snipe-IT:
        // 1. By Asset Tag
        $assetResponse = $this->snipe->getHardwareByAssetTag($query);
        \Log::info('🏷️ Search by Asset Tag', ['found' => !empty($assetResponse['rows'])]);

        // 2. By Serial
        if (empty($assetResponse['rows'])) {
            $assetResponse = $this->snipe->getHardwareBySerial($query);
            \Log::info('🔢 Search by Serial', ['found' => !empty($assetResponse['rows'])]);
        }

        // 3. By General Search
        if (empty($assetResponse['rows'])) {
            $assetResponse = $this->snipe->request('hardware', ['search' => $query, 'limit' => 1]);
            \Log::info('🔍 General Search', ['found' => !empty($assetResponse['rows'])]);
        }

        // 4. By ID if numeric
        if (empty($assetResponse['rows']) && is_numeric($query)) {
            $record = $this->snipe->getHardware((int) $query);
            if (!empty($record['id'])) {
                $assetResponse = ['rows' => [$record]];
                \Log::info('🆔 Search by ID', ['found' => true]);
            }
        }

        if (empty($assetResponse['rows'])) {
            \Log::warning('❌ Asset not found in Snipe-IT', ['query' => $query]);
            return response()->json(['message' => 'Asset tidak tersedia atau tidak ditemukan di Snipe-IT.'], 422);
        }

        $asset = $assetResponse['rows'][0];
        $assetId = (int) ($asset['id'] ?? data_get($asset, 'rows.0.id', 0));

        $asset = $this->snipe->getHardware($assetId) ?: $asset;
        $assetId = (int) ($asset['id'] ?? data_get($asset, 'rows.0.id', $assetId));

        if ($assetId <= 0) {
            \Log::warning('❌ Invalid asset ID', ['assetId' => $assetId]);
            return response()->json(['message' => 'Asset tidak memiliki ID yang valid.'], 422);
        }

        \Log::info('✅ Asset found', ['assetId' => $assetId, 'asset_tag' => $asset['asset_tag'] ?? 'N/A']);

        if ($this->isExcludedAsset($asset)) {
            \Log::warning('⛔ Asset is excluded (broken status)');
            return response()->json(['message' => 'Asset berstatus Broken dan tidak termasuk dalam sesi Stock Opname.'], 422);
        }

        $sessionItem = $session->items()
            ->with('verifier:id,name')
            ->where('snipeit_asset_id', $assetId)
            ->first();

        if (!$sessionItem) {
            \Log::warning('❌ Asset not in audit session', [
                'assetId' => $assetId,
                'sessionId' => $session->id,
                'totalItemsInSession' => $session->items()->count()
            ]);
            return response()->json(['message' => 'Asset tidak termasuk dalam daftar sesi Stock Opname ini.'], 422);
        }

        if ($sessionItem->verified_at) {
            $verifiedBy = $sessionItem->verifier?->name ?? 'user lain';
            $verifiedAt = $sessionItem->verified_at->format('d/m/Y H:i');

            \Log::info('⚠️ Asset already audited', [
                'verifiedBy' => $verifiedBy,
                'verifiedAt' => $verifiedAt
            ]);

            return response()->json([
                'message' => "Asset sudah diaudit oleh {$verifiedBy} pada {$verifiedAt}.",
                'already_audited' => true,
                'verified_by' => $verifiedBy,
                'verified_at' => $sessionItem->verified_at->toIso8601String(),
            ], 422);
        }

        \Log::info('🎉 Scan successful, returning asset data');

        $assetData = [
            'id'          => $assetId,
            'name'        => $asset['name'] ?? $asset['model']['name'] ?? 'Hardware Asset',
            'asset_tag'   => $asset['asset_tag'] ?? '',
            'tag'         => $asset['asset_tag'] ?? '',
            'serial'      => $asset['serial'] ?? '',
            'model'       => $asset['model']['name'] ?? '-',
            'category'    => $asset['category']['name'] ?? 'Hardware',
            'location'    => $asset['location']['name'] ?? 'N/A',
            'assigned_to' => $asset['assigned_to']['name'] ?? 'Available',
            'user'        => $asset['assigned_to']['name'] ?? 'Available',
            'image'       => $asset['image'] ?? null,
            'status'      => $asset['status_label']['name'] ?? 'Deployable',
            'session_item_id' => $sessionItem->id,
            'department'  => data_get($asset, 'assigned_to.department.name') ?? data_get($asset, 'assigned_to.department') ?? '',
            'model'        => data_get($asset, 'model.name', '-'),
            'category'     => data_get($asset, 'category.name', '-'),
            'company'      => data_get($asset, 'company.name', '-'),
            'notes'        => $asset['notes'] ?? '',
            'location_id'  => data_get($asset, 'location.id') ?? data_get($asset, 'rtd_location.id'),
            'status_id'    => data_get($asset, 'status_label.id'),
        ];

        return response()->json([
            'asset' => $assetData,
            ...$assetData,
        ]);
    }

    public function updateAsset(Request $request, AuditSession $session, AuditItem $item)
    {
        if ($item->audit_session_id !== $session->id) {
            return response()->json(['message' => 'Asset tidak termasuk dalam sesi audit ini.'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'asset_tag' => 'required|string|max:255',
            'serial' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $payload = collect([
            'name' => $validated['name'],
            'asset_tag' => $validated['asset_tag'],
            'serial' => $validated['serial'] ?? '',
            'notes' => $validated['notes'] ?? '',
        ])->when($validated['location'] ?? null, function ($fields) use ($validated) {
            $locationResponse = $this->snipe->request('locations', [
                'search' => $validated['location'],
            ], true);
            $location = collect($locationResponse['rows'] ?? [])->first(
                fn (array $row) => strcasecmp((string) ($row['name'] ?? ''), $validated['location']) === 0
            );

            if (!$location) {
                abort(response()->json([
                    'message' => "Lokasi '{$validated['location']}' tidak ditemukan di Snipe-IT.",
                ], 422));
            }

            return $fields->put('location_id', (int) $location['id']);
        })->all();

        $response = $this->snipe->updateRecord('hardware', $item->snipeit_asset_id, $payload);

        if (($response['status'] ?? 'error') !== 'success') {
            return response()->json([
                'message' => 'Data asset gagal diperbarui di Snipe-IT.',
            ], 422);
        }

        $item->update([
            'asset_tag' => $validated['asset_tag'],
            'serial' => $validated['serial'] ?? '',
            'asset_name' => $validated['name'],
            'physical_location' => $validated['location'] ?? $item->physical_location,
            'notes' => $validated['notes'] ?? $item->notes,
        ]);

        $this->snipe->flushCacheForAsset('assets', $item->snipeit_asset_id);

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function verify(Request $request, AuditSession $session)
    {
        $validated = $request->validate([
            'snipeit_asset_id'  => 'required|integer',
            'asset_tag'         => 'required|string',
            'serial'            => 'nullable|string',
            'status'            => 'required|in:Match,Mismatch',
            'physical_location' => 'nullable|string',
            'physical_user'     => 'nullable|string',
            'note'              => 'nullable|string',
            'expected_location' => 'nullable|string',
            'expected_user'     => 'nullable|string',
        ]);

        $validated['serial'] = $validated['serial'] ?? '';
        $sessionItem = $session->items()->where('snipeit_asset_id', $validated['snipeit_asset_id'])->first();

        if (!$sessionItem) {
            return response()->json(['message' => 'Asset tidak termasuk dalam daftar sesi Stock Opname ini.'], 422);
        }

        $validated['expected_location'] = $sessionItem->expected_location;
        $validated['expected_department'] = $sessionItem->expected_department;
        $validated['expected_user'] = $sessionItem->expected_user;

        if ($validated['status'] === 'Mismatch'
            && trim((string) $validated['physical_location']) === trim((string) $validated['expected_location'])
            && trim((string) $validated['physical_user']) === trim((string) $validated['expected_user'])) {
            return response()->json(['message' => 'Mismatch harus memiliki perubahan lokasi atau pengguna fisik.'], 422);
        }

        $item = $sessionItem->fill([
            'asset_tag' => $validated['asset_tag'],
            'serial' => $validated['serial'],
            'status' => $validated['status'],
            'physical_location' => $validated['physical_location'],
            'physical_user' => $validated['physical_user'],
            'notes' => $validated['note'] ?? null,
            'expected_location' => $validated['expected_location'],
            'expected_department' => $validated['expected_department'],
            'expected_user' => $validated['expected_user'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);
        $item->save();

        ActionLog::create([
            'user_id' => $request->user()->id,
            'action_type' => 'audit_verified',
            'item_type' => AuditItem::class,
            'item_id' => $item->id,
            'target_type' => AuditSession::class,
            'target_id' => $session->id,
            'snipeit_id' => $item->snipeit_asset_id,
            'snipeit_type' => 'assets',
            'note' => "Stock Opname {$validated['status']}: {$item->asset_tag}",
            'log_meta' => [
                'audit_session_id' => $session->id,
                'audit_session_name' => $session->name,
                'status' => $item->status,
                'verified_at' => $item->verified_at?->toIso8601String(),
                'physical_location' => $item->physical_location,
                'physical_user' => $item->physical_user,
            ],
        ]);

        return response()->json([
            'success' => true,
            'item' => $item->load('verifier')
        ]);
    }

    public function syncItem(Request $request, AuditSession $session, AuditItem $item)
    {
        if ($item->audit_session_id !== $session->id) {
            return response()->json(['message' => 'Item tidak termasuk dalam sesi audit ini.'], 404);
        }

        if (!$item->snipeit_asset_id || !$item->physical_location) {
            return response()->json(['message' => 'Data item tidak lengkap untuk sinkronisasi.'], 422);
        }

        // Search for location ID in Snipe-IT
        $locationResponse = $this->snipe->request('locations', ['search' => $item->physical_location]);
        $locationId = collect($locationResponse['rows'] ?? [])->firstWhere('name', $item->physical_location)['id'] ?? null;

        if (!$locationId) {
            return response()->json(['message' => "Lokasi '{$item->physical_location}' tidak ditemukan di Snipe-IT. Silakan buat lokasi tersebut di Snipe-IT terlebih dahulu."], 422);
        }

        // Update Snipe-IT
        $this->snipe->updateRecord('hardware', $item->snipeit_asset_id, [
            'location_id' => $locationId,
        ]);

        $item->update(['is_synced' => true]);

        \App\Models\ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'sync_audit_item',
            'target_type' => 'AuditItem',
            'target_id' => $item->id,
            'details' => "Synced location for {$item->asset_tag} to {$item->physical_location}",
        ]);

        return response()->json(['success' => true]);
    }

    public function complete(AuditSession $session)
    {
        abort_unless($session->status === 'Open', 422, 'Sesi audit ini sudah selesai.');

        $missingCount = $session->items()->where('status', 'Missing')->whereNull('verified_at')->count();
        $totalUnverified = $session->items()->whereNull('verified_at')->count();

        // Block only if there are unverified items that are NOT "Missing" default status
        // (i.e. items that have been scanned/touched but verification was not completed)
        // Missing items with no verified_at are legitimately "not found" — allow completion.
        // However if ALL items are unverified (nothing scanned at all), block.
        $verifiedCount = $session->items()->whereNotNull('verified_at')->count();
        if ($verifiedCount === 0 && $session->items()->exists()) {
            return back()->withErrors([
                'audit' => 'Belum ada asset yang diproses. Lakukan scan minimal satu asset sebelum menyelesaikan audit.',
            ]);
        }

        $session->update([
            'status'       => 'Completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('audit.index')->with('success', 'Sesi Audit berhasil diselesaikan.');

    }

    public function export(AuditSession $session)
    {
        $session->load(['items' => fn($q) => $q->with('verifier:id,name')->orderBy('expected_location'), 'creator']);

        $items      = $session->items;
        $total      = $items->count();
        $matchCount = $items->where('status', 'Match')->count();
        $mismatchCount = $items->where('status', 'Mismatch')->count();
        $missingCount  = $items->where('status', 'Missing')->count();
        $verifiedCount = $items->whereNotNull('verified_at')->count();
        $completionPct = $total > 0 ? round(($verifiedCount / $total) * 100, 1) : 0;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $fill   = \PhpOffice\PhpSpreadsheet\Style\Fill::class;
        $color  = \PhpOffice\PhpSpreadsheet\Style\Color::class;
        $border = \PhpOffice\PhpSpreadsheet\Style\Border::class;
        $align  = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $coord  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::class;

        // ── Sheet 1: Summary ─────────────────────────────────
        $summary = $spreadsheet->getActiveSheet()->setTitle('Ringkasan');

        // Title block
        $summary->mergeCells('A1:F1');
        $summary->setCellValue('A1', 'LAPORAN STOCK OPNAME — ' . strtoupper($session->name));
        $summary->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => $fill::FILL_SOLID, 'startColor' => ['argb' => 'FF003628']],
            'alignment' => ['horizontal' => $align::HORIZONTAL_CENTER],
        ]);
        $summary->getRowDimension(1)->setRowHeight(24);

        // Info rows
        $infoRows = [
            ['Nama Sesi',       $session->name],
            ['Status',          $session->status],
            ['Dibuat Oleh',     $session->creator?->name ?? 'System'],
            ['Tanggal Dibuat',  $session->created_at->format('d M Y H:i')],
            ['Tanggal Selesai', $session->completed_at?->format('d M Y H:i') ?? '-'],
            ['Dicetak Pada',    now()->format('d M Y H:i')],
        ];
        $r = 2;
        foreach ($infoRows as [$label, $value]) {
            $summary->setCellValue("A{$r}", $label);
            $summary->setCellValue("B{$r}", $value);
            $summary->getStyle("A{$r}")->getFont()->setBold(true);
            $summary->getStyle("A{$r}")->getFill()->setFillType($fill::FILL_SOLID)->getStartColor()->setARGB('FFF0FDF4');
            $r++;
        }

        // Spacer
        $r++;

        // Stats table header
        $statHeaders = ['Statistik', 'Jumlah', 'Persentase'];
        foreach ($statHeaders as $ci => $h) {
            $summary->setCellValue($coord::stringFromColumnIndex($ci + 1) . $r, $h);
        }
        $summary->getStyle("A{$r}:C{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => $fill::FILL_SOLID, 'startColor' => ['argb' => 'FF003628']],
            'alignment' => ['horizontal' => $align::HORIZONTAL_CENTER],
        ]);
        $r++;

        // Stats rows
        $statRows = [
            ['Total Aset',          $total,         '100%',                   'FFF0FDF4'],
            ['Match (Sesuai)',       $matchCount,    $total > 0 ? round($matchCount / $total * 100, 1) . '%' : '0%',      'FFD1FAE5'],
            ['Mismatch (Beda)',      $mismatchCount, $total > 0 ? round($mismatchCount / $total * 100, 1) . '%' : '0%',   'FFFEF9C3'],
            ['Missing (Belum Scan)', $missingCount,  $total > 0 ? round($missingCount / $total * 100, 1) . '%' : '0%',   'FFFFE4E6'],
            ['Completion Rate',      "{$verifiedCount}/{$total}", "{$completionPct}%",                                   'FFEFF6FF'],
        ];
        foreach ($statRows as [$label, $count, $pct, $bg]) {
            $summary->setCellValue("A{$r}", $label);
            $summary->setCellValue("B{$r}", $count);
            $summary->setCellValue("C{$r}", $pct);
            $summary->getStyle("A{$r}:C{$r}")->getFill()->setFillType($fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            $summary->getStyle("B{$r}:C{$r}")->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER);
            $r++;
        }

        // Add border to stats
        $summary->getStyle('A' . ($r - count($statRows) - 1) . ':C' . ($r - 1))->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => $border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']],
            ],
        ]);

        // Department breakdown
        $r += 2;
        $summary->mergeCells("A{$r}:E{$r}");
        $summary->setCellValue("A{$r}", 'BREAKDOWN PER DEPARTEMEN');
        $summary->getStyle("A{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => $fill::FILL_SOLID, 'startColor' => ['argb' => 'FF003628']],
        ]);
        $r++;
        foreach (['Departemen', 'Total', 'Match', 'Mismatch', 'Missing'] as $ci => $h) {
            $summary->setCellValue($coord::stringFromColumnIndex($ci + 1) . $r, $h);
        }
        $summary->getStyle("A{$r}:E{$r}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => $fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']],
            'alignment' => ['horizontal' => $align::HORIZONTAL_CENTER],
        ]);
        $r++;

        $byDept = $items->whereNotNull('verified_at')
            ->groupBy(fn($i) => $i->expected_department ?: 'Tidak Ada Departemen')
            ->sortKeys();

        foreach ($byDept as $dept => $deptItems) {
            $summary->setCellValue("A{$r}", $dept);
            $summary->setCellValue("B{$r}", $deptItems->count());
            $summary->setCellValue("C{$r}", $deptItems->where('status', 'Match')->count());
            $summary->setCellValue("D{$r}", $deptItems->where('status', 'Mismatch')->count());
            $summary->setCellValue("E{$r}", $deptItems->where('status', 'Missing')->count());
            $summary->getStyle("B{$r}:E{$r}")->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER);
            $r++;
        }

        foreach (range(1, 6) as $col) {
            $summary->getColumnDimension($coord::stringFromColumnIndex($col))->setAutoSize(true);
        }

        // ── Sheet 2: Detail ───────────────────────────────────
        $detail = $spreadsheet->createSheet()->setTitle('Detail Audit');

        $detailHeaders = [
            'No', 'Asset Tag', 'Serial', 'Nama Aset', 'Departemen',
            'Lok. Expected', 'Lok. Fisik', 'User Expected', 'User Fisik',
            'Status', 'Is Synced', 'Verifikator', 'Tgl Verifikasi', 'Catatan',
        ];
        foreach ($detailHeaders as $ci => $h) {
            $detail->setCellValue($coord::stringFromColumnIndex($ci + 1) . '1', $h);
        }
        $detail->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => $fill::FILL_SOLID, 'startColor' => ['argb' => 'FF003628']],
            'alignment' => ['horizontal' => $align::HORIZONTAL_CENTER],
        ]);

        $statusColors = [
            'Match'    => 'FFD1FAE5',
            'Mismatch' => 'FFFEF3C7',
            'Missing'  => 'FFFFE4E6',
        ];

        $row = 2;
        $no = 1;
        foreach ($items as $item) {
            $detail->setCellValue('A' . $row, $no++);
            $detail->setCellValue('B' . $row, $item->asset_tag);
            $detail->setCellValue('C' . $row, $item->serial);
            $detail->setCellValue('D' . $row, $item->asset_name);
            $detail->setCellValue('E' . $row, $item->expected_department);
            $detail->setCellValue('F' . $row, $item->expected_location);
            $detail->setCellValue('G' . $row, $item->physical_location);
            $detail->setCellValue('H' . $row, $item->expected_user);
            $detail->setCellValue('I' . $row, $item->physical_user);
            $detail->setCellValue('J' . $row, $item->status);
            $detail->setCellValue('K' . $row, $item->is_synced ? 'Ya' : 'Tidak');
            $detail->setCellValue('L' . $row, $item->verifier?->name);
            $detail->setCellValue('M' . $row, $item->verified_at?->format('d/m/Y H:i'));
            $detail->setCellValue('N' . $row, $item->notes);

            $rowBg = $statusColors[$item->status] ?? 'FFFFFFFF';
            $detail->getStyle("A{$row}:N{$row}")->getFill()->setFillType($fill::FILL_SOLID)->getStartColor()->setARGB($rowBg);
            $detail->getStyle("A{$row}:N{$row}")->getBorders()->getAllBorders()->setBorderStyle($border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

            $row++;
        }

        foreach (range(1, 14) as $col) {
            $detail->getColumnDimension($coord::stringFromColumnIndex($col))->setAutoSize(true);
        }

        // Set active sheet to summary
        $spreadsheet->setActiveSheetIndex(0);

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'StockOpname_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $session->name) . '_' . now()->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function exportPdf(AuditSession $session)
    {
        $session->load([
            'creator:id,name',
            'items' => fn($q) => $q->with('verifier:id,name')->orderBy('expected_location'),
        ]);

        $pdf = Pdf::loadView('audit.report_pdf', compact('session'))
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => false,
                'defaultFont'          => 'dejavusans',
                'dpi'                  => 110,
            ]);

        $fileName = 'StockOpname_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $session->name) . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->download($fileName);
    }

    private function isExcludedAsset(array $asset): bool
    {
        $status = strtolower(trim((string) (data_get($asset, 'status_label.name') ?? '')));
        $statusType = strtolower(trim((string) (data_get($asset, 'status_label.status_type') ?? '')));

        return in_array($status, ['broken', 'rusak', 'out for repair', 'mati', 'damaged'], true)
            || $statusType === 'broken';
    }

    private function fetchAuditAssets(): \Illuminate\Support\Collection
    {
        return collect($this->snipe->fetchRows('hardware', [], 500, true))
            ->reject(fn (array $asset) => $this->isExcludedAsset($asset))
            ->unique(fn (array $asset) => (int) ($asset['id'] ?? 0))
            ->filter(fn (array $asset) => (int) ($asset['id'] ?? 0) > 0)
            ->values();
    }

    private function seedSessionItems(AuditSession $session): void
    {
        $assets = $this->fetchAuditAssets();

        if ($assets->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($session, $assets): void {
            if ($session->items()->exists()) {
                return;
            }

            $session->items()->createMany($assets->map(fn (array $asset) => [
                'snipeit_asset_id' => (int) $asset['id'],
                'asset_tag' => (string) ($asset['asset_tag'] ?? ''),
                'serial' => (string) ($asset['serial'] ?? ''),
                'asset_name' => (string) ($asset['name'] ?? data_get($asset, 'model.name', 'Hardware Asset')),
                'status' => 'Missing',
                'expected_location' => (string) (data_get($asset, 'location.name') ?? ''),
                'expected_department' => $this->resolveAssetDepartment($asset),
                'expected_user' => (string) (data_get($asset, 'assigned_to.name') ?? 'Available'),
            ])->all());
        });
    }

    private function resolveAssetDepartment(array $asset): string
    {
        $department = data_get($asset, 'assigned_to.department.name')
            ?? data_get($asset, 'assigned_to.department');

        if ($department) {
            return (string) $department;
        }

        $snipeUserId = (int) data_get($asset, 'assigned_to.id', 0);

        return $snipeUserId > 0
            ? (string) (User::where('snipeit_user_id', $snipeUserId)->value('department') ?? '')
            : '';
    }

    private function backfillDepartments(AuditSession $session): void
    {
        $items = $session->items()->where(function ($query): void {
            $query->whereNull('expected_department')->orWhere('expected_department', '');
        })->get();

        foreach ($items as $item) {
            $asset = $this->snipe->getHardware((int) $item->snipeit_asset_id);
            $department = $this->resolveAssetDepartment($asset ?? []);

            if ($department !== '') {
                $item->update(['expected_department' => $department]);
            }
        }
    }
}
