<?php

namespace App\Http\Controllers;

use App\Models\AuditItem;
use App\Models\AuditSession;
use App\Services\SnipeItService;
use Illuminate\Http\Request;
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

        $session = AuditSession::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'status' => 'Open',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('audit.show', $session->id);
    }

    public function show(AuditSession $session)
    {
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

    public function scan(Request $request, AuditSession $session)
    {
        $request->validate([
            'search' => 'required|string', // asset tag or serial
        ]);

        $query = $request->input('search');
        
        // Search in Snipe-IT
        $assetResponse = $this->snipe->getHardwareBySerial($query);
        if (empty($assetResponse['rows'])) {
            $assetResponse = $this->snipe->request('hardware', ['asset_tag' => $query]);
        }

        if (empty($assetResponse['rows'])) {
            return response()->json(['message' => 'Asset tidak ditemukan di Snipe-IT.'], 404);
        }

        $asset = $assetResponse['rows'][0];

        return response()->json([
            'id' => $asset['id'],
            'name' => $asset['name'] ?? $asset['model']['name'],
            'tag' => $asset['asset_tag'],
            'serial' => $asset['serial'],
            'location' => $asset['location']['name'] ?? 'N/A',
            'user' => $asset['assigned_to']['name'] ?? 'Available',
            'image' => $asset['image'] ?? null,
        ]);
    }

    public function verify(Request $request, AuditSession $session)
    {
        $validated = $request->validate([
            'snipeit_asset_id' => 'required|integer',
            'asset_tag' => 'required|string',
            'serial' => 'required|string',
            'status' => 'required|string', // Match, Wrong Location, etc.
            'physical_location' => 'nullable|string',
            'physical_user' => 'nullable|string',
            'note' => 'nullable|string',
            'expected_location' => 'nullable|string',
            'expected_user' => 'nullable|string',
        ]);

        $item = $session->items()->updateOrCreate(
            ['snipeit_asset_id' => $validated['snipeit_asset_id']],
            array_merge($validated, [
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
            ])
        );

        // If status is "Match", update Snipe-IT last audit date
        if ($validated['status'] === 'Match') {
            $this->snipe->updateRecord('hardware', $validated['snipeit_asset_id'], [
                'last_audit_date' => now()->toDateString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'item' => $item->load('verifier')
        ]);
    }

    public function syncItem(Request $request, AuditSession $session, AuditItem $item)
    {
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
        $session->update([
            'status' => 'Completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('audit.index')->with('success', 'Sesi Audit berhasil diselesaikan.');
    }

    public function export(AuditSession $session)
    {
        $session->load(['items', 'creator']);
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Header
        $headers = [
            'ID', 'Asset Tag', 'Serial', 'Asset Name', 
            'Expected Location', 'Physical Location', 
            'Expected User', 'Physical User', 
            'Status', 'Verifier', 'Date Verified', 'Notes'
        ];
        
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }
        
        // Style Header
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);
        $sheet->getStyle('A1:L1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF003628');
        $sheet->getStyle('A1:L1')->getFont()->getColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);

        // Data
        $row = 2;
        foreach ($session->items as $item) {
            $sheet->setCellValueByColumnAndRow(1, $row, $item->id);
            $sheet->setCellValueByColumnAndRow(2, $row, $item->asset_tag);
            $sheet->setCellValueByColumnAndRow(3, $row, $item->serial);
            $sheet->setCellValueByColumnAndRow(4, $row, $item->asset_name);
            $sheet->setCellValueByColumnAndRow(5, $row, $item->expected_location);
            $sheet->setCellValueByColumnAndRow(6, $row, $item->physical_location);
            $sheet->setCellValueByColumnAndRow(7, $row, $item->expected_user);
            $sheet->setCellValueByColumnAndRow(8, $row, $item->physical_user);
            $sheet->setCellValueByColumnAndRow(9, $row, $item->status);
            $sheet->setCellValueByColumnAndRow(10, $row, $item->verifier?->name);
            $sheet->setCellValueByColumnAndRow(11, $row, $item->verified_at);
            $sheet->setCellValueByColumnAndRow(12, $row, $item->note);
            $row++;
        }

        // Auto-size columns
        foreach (range(1, 12) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = "Audit_Report_{$session->id}_" . now()->format('YmdHis') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
