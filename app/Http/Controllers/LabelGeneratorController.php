<?php

namespace App\Http\Controllers;

use App\Services\SnipeItService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Process\Process;

class LabelGeneratorController extends Controller
{
    public function __construct(
        protected SnipeItService $snipe
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $assets = [];

        if ($search !== '') {
            $payload = $this->snipe->getHardware([
                'search' => $search,
                'limit'  => 30,
            ]);
            $rawRows = $payload['rows'] ?? [];

            $assets = array_map(function ($row) {
                return [
                    'id'          => $row['id'] ?? 0,
                    'name'        => $row['name'] ?? '',
                    'asset_tag'   => $row['asset_tag'] ?? '',
                    'serial'      => $row['serial'] ?? '',
                    'model'       => data_get($row, 'model.name', ''),
                    'category'    => data_get($row, 'category.name', ''),
                    'location'    => data_get($row, 'location.name', ''),
                    'company'     => data_get($row, 'company.name', ''),
                    'status'      => data_get($row, 'status_label.name', ''),
                    'status_type' => data_get($row, 'status_label.status_type', ''),
                    'qr_url'      => url('a/' . ($row['serial'] ?: $row['asset_tag'] ?: $row['id'])),
                ];
            }, $rawRows);
        }

        return Inertia::render('LabelGenerator/Index', [
            'assets'       => $assets,
            'initialSearch' => $search,
        ]);
    }

    public function pdf(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $size = (string) $request->query('size', 'xs');
        if (!in_array($size, ['xs', 'sm', 'md', 'lg', 'xl'], true)) {
            $size = 'xs';
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'Pilih minimal satu asset untuk dicetak.');
        }

        $assets = $ids->map(function (int $id) {
            $record = $this->snipe->getHardware($id);
            if (!$record || empty($record['id'])) return null;

            $assetTag = (string) ($record['asset_tag'] ?? $record['name'] ?? $id);
            return [
                'name' => $record['name'] ?? $assetTag,
                'asset_tag' => $assetTag,
                'serial' => $record['serial'] ?? '',
                'location' => data_get($record, 'location.name', ''),
                'public_url' => url('a/' . ($record['serial'] ?? $assetTag)),
            ];
        })->filter()->values()->all();

        if ($assets === []) {
            return back()->with('error', 'Asset tidak ditemukan di Snipe-IT.');
        }

        $browserPath = $this->pdfBrowserPath();
        if (!$browserPath) return back()->with('error', 'Browser PDF belum tersedia di server.');

        $tempDirectory = storage_path('app/label-temp');
        if (!is_dir($tempDirectory)) mkdir($tempDirectory, 0777, true);
        $htmlPath = $tempDirectory . DIRECTORY_SEPARATOR . Str::uuid() . '.html';
        $pdfPath = storage_path('app/public/asset-labels/asset-labels.pdf');
        if (!is_dir(dirname($pdfPath))) mkdir(dirname($pdfPath), 0777, true);
        file_put_contents($htmlPath, view('asset.label_pdf_batch', [
            'assets' => $assets,
            'size' => $size,
        ])->render());

        $process = new Process([
            $browserPath, '--headless=new', '--no-sandbox', '--disable-dev-shm-usage',
            '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--allow-file-access-from-files', '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw', '--virtual-time-budget=12000',
            '--print-to-pdf=' . $pdfPath, 'file:///' . str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(60);
        $process->run();
        @unlink($htmlPath);

        if (!$process->isSuccessful() || !is_file($pdfPath)) {
            Log::error('Asset labels PDF generation failed', ['error' => $process->getErrorOutput()]);
            return back()->with('error', 'PDF label gagal dibuat.');
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="asset-labels.pdf"',
        ]);
    }

    private function pdfBrowserPath(): ?string
    {
        foreach (array_filter([
            trim((string) config('services.pdf.browser_path', '')),
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            '/usr/bin/chromium', '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable',
        ]) as $path) {
            if (is_file($path)) return $path;
        }
        return null;
    }
}
