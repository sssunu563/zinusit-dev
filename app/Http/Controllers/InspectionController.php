<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\Inspection;
use App\Services\AssetNoteFormatterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InspectionController extends Controller
{
    // Helpers

    /**
     * Log an inspection action to the local ActionLog.
     */
    private function logInspection(string $actionType, Inspection $inspection, string $note, array $meta = []): void
    {
        try {
            ActionLog::create([
                'user_id'     => auth()->id(),
                'action_type' => $actionType,
                'item_type'   => Inspection::class,
                'item_id'     => $inspection->id,
                'note'        => $note,
                'log_meta'    => array_merge([
                    'report_id'   => $inspection->report_id,
                    'report_type' => $inspection->report_type,
                    'user'        => $inspection->user,
                    'location'    => $inspection->location,
                    'device_name' => $inspection->device_name,
                    'asset_tag'   => $inspection->asset_tag,
                ], $meta),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to write inspection action log', [
                'action'        => $actionType,
                'inspection_id' => $inspection->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate the next Report ID.
     * Format: IR-{COMPANY_ABBR}-{YYMM}-{XXXXX}
     * e.g.    IR-ZGI-2604-00001
     */
    private function generateReportId(string $company, string $date): string
    {
        // Extract first letter of each word from company (max 3)
        $words = preg_split('/\s+/', strtoupper(trim($company)));
        $abbr  = implode('', array_slice(array_map(
            fn ($w) => substr($w, 0, 1),
            array_filter($words)
        ), 0, 3));

        if ($abbr === '') {
            $abbr = 'IR';
        }

        // yymm from date
        $dt   = \Carbon\Carbon::parse($date);
        $yymm = $dt->format('ym');

        // next sequential number
        $lastId = Inspection::max('id') ?? 0;
        $seq    = str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);

        return "IR-{$abbr}-{$yymm}-{$seq}";
    }

    /**
     * Handle photo upload. Stores raw; compression is done client-side.
     */
    private function handlePhotoUpload(Request $request, ?string $existing = null): ?string
    {
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($existing) {
                Storage::disk('public')->delete($existing);
            }
            return $request->file('photo')->store('inspection-photos', 'public');
        }

        return $existing;
    }

    // CRUD

    public function index(Request $request): \Inertia\Response
    {
        $filters = $request->only(['search', 'location', 'department', 'from_date', 'to_date', 'status']);
        
        $query = Inspection::select([
            'id', 'report_id', 'report_type', 'location', 'user', 'department',
            'device_name', 'asset_tag', 'asset_snapshot', 'issue_description', 'date', 'created_at',
            'completed_at', 'completed_pdf_path', 'snipeit_asset_id',
        ])
        // Compute signed_count in SQL to avoid decrypting encrypted fields per row
        ->selectRaw("
            (CASE WHEN it_signature IS NOT NULL AND it_signature != '' THEN 1 ELSE 0 END +
             CASE WHEN checked_signature IS NOT NULL AND checked_signature != '' THEN 1 ELSE 0 END +
             CASE WHEN user_signature IS NOT NULL AND user_signature != '' THEN 1 ELSE 0 END +
             CASE WHEN leader_signature IS NOT NULL AND leader_signature != '' THEN 1 ELSE 0 END
            ) as signed_count
        ");

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('report_id', 'like', $search)
                  ->orWhere('user', 'like', $search)
                  ->orWhere('issue_description', 'like', $search)
                  ->orWhere('report_type', 'like', $search)
                  ->orWhere('device_name', 'like', $search);
            });
        }

        if (!empty($filters['location'])) {
            $query->where('location', $filters['location']);
        }

        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }

        if (($filters['status'] ?? 'active') === 'completed') {
            $query->whereNotNull('completed_at');
        } elseif (($filters['status'] ?? 'active') === 'cancelled') {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereNull('completed_at');
        }

        $inspections = $query->latest('created_at')->paginate(15)->withQueryString();

        // Keep the public document link available for every inspection state.
        $inspections->getCollection()->transform(function ($inspection) {
            $inspection->share_url = $inspection->completed_at
                ? null
                : \Illuminate\Support\Facades\URL::temporarySignedRoute(
                    'inspection.share', now()->addDays(7), ['inspection' => $inspection->id]
                );
            return $inspection;
        });

        return Inertia::render('Inspection/Index', [
            'inspections'       => $inspections,
            'filters'           => $filters,
            'activeCount'       => Inspection::whereNull('completed_at')->count(),
            'completedCount'    => Inspection::whereNotNull('completed_at')->count(),
            'cancelledCount'    => 0,
            'locationOptions'   => \Illuminate\Support\Facades\Cache::remember(
                'inspection_location_options', 300,
                fn () => Inspection::distinct()->whereNotNull('location')->orderBy('location')->pluck('location')->toArray()
            ),
            'departmentOptions' => \Illuminate\Support\Facades\Cache::remember(
                'inspection_department_options', 300,
                fn () => Inspection::distinct()->whereNotNull('department')->orderBy('department')->pluck('department')->toArray()
            ),
        ]);
    }

    public function create(Request $request): \Inertia\Response
    {
        $nextId    = (Inspection::max('id') ?? 0) + 1;
        $initialData = [];

        // Pre-fill from asset if coming from asset detail page
        if ($request->filled('from_asset')) {
            try {
                $snipe    = app(\App\Services\SnipeItService::class);
                $assetId  = (int) $request->query('from_asset');
                $asset    = $snipe->request("hardware/{$assetId}");

                if (!empty($asset['id'])) {
                    // Map category to device_category
                    $catName = strtolower($asset['category']['name'] ?? '');
                    $deviceCategory = 'other';
                    if (str_contains($catName, 'pc') || str_contains($catName, 'computer') || str_contains($catName, 'desktop')) $deviceCategory = 'pc';
                    elseif (str_contains($catName, 'laptop') || str_contains($catName, 'notebook')) $deviceCategory = 'laptop';
                    elseif (str_contains($catName, 'printer')) $deviceCategory = 'printer';
                    elseif (str_contains($catName, 'monitor') || str_contains($catName, 'display')) $deviceCategory = 'monitor';
                    elseif (str_contains($catName, 'network') || str_contains($catName, 'switch') || str_contains($catName, 'router')) $deviceCategory = 'network';

                    $initialData['snipeit_asset_id'] = $asset['id'];
                    $initialData['device_name']      = $asset['name'] ?? '';
                    $initialData['asset_tag']        = $asset['asset_tag'] ?? '';
                    $initialData['serial_number']    = $asset['serial'] ?? '';
                    $initialData['device_category']  = $deviceCategory;
                    $initialData['location']         = $asset['location']['name'] ?? '';
                    $initialData['asset_snapshot']   = json_encode([
                        'id'       => $asset['id'],
                        'name'     => $asset['name'],
                        'asset_tag'=> $asset['asset_tag'],
                        'serial'   => $asset['serial'],
                        'category' => $asset['category']['name'] ?? null,
                    ]);

                    // If asset is assigned to a user, pre-fill user info
                    $assignedTo = $asset['assigned_to'] ?? null;
                    if (!empty($assignedTo['id']) && ($assignedTo['type'] ?? '') === 'user') {
                        $user = $snipe->request("users/{$assignedTo['id']}");
                        if (!empty($user['id'])) {
                            $firstName = trim((string) ($user['first_name'] ?? ''));
                            $lastName  = trim((string) ($user['last_name'] ?? ''));
                            $fullName  = trim($firstName . ' ' . $lastName) ?: ($user['name'] ?? '');

                            $initialData['user']       = $fullName;
                            $initialData['email']      = $user['email'] ?? '';
                            $initialData['company']    = $user['company']['name'] ?? '';
                            $initialData['department'] = $user['department']['name'] ?? '';
                            $initialData['dept_head']  = $user['manager']['name'] ?? '';
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to pre-fill inspection from asset', [
                    'asset_id' => $request->query('from_asset'),
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return Inertia::render('Inspection/Create', [
            'nextSequence' => $nextId,
            'initialData'  => $initialData,
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'report_type'       => 'required|string|max:255',
            'company'           => 'required|string|max:255',
            'department'        => 'required|string|max:255',
            'dept_head'         => 'nullable|string|max:255',
            'it_staff'          => 'nullable|string|max:255',
            'user'              => 'required|string|max:255',
            'email'             => 'nullable|email|max:255',
            'date'              => 'required|date',
            'location'          => 'required|string|max:255',
            'device_category'   => 'nullable|string|max:50',
            'device_name'       => 'nullable|string|max:255',
            'asset_tag'         => 'nullable|string|max:255',
            'serial_number'     => 'nullable|string|max:255',
            'asset_snapshot'    => 'nullable|string',
            'snipeit_asset_id'  => 'nullable|integer',
            'checked_by'        => 'required|string|max:255',
            'checked_date'      => 'required|date',
            'issue_description' => 'required|string',
            'solution'          => 'required|string',
            'remarks'           => 'nullable|string',
            'photo'             => 'nullable|image|max:5120',
            // IDs for edit pre-population
            'user_id'           => 'nullable|integer',
            'it_staff_id'       => 'nullable|integer',
            'checked_by_id'     => 'nullable|integer',
        ]);

        try {
            $photoPath = $this->handlePhotoUpload($request);
            $reportId  = $this->generateReportId($validated['company'], $validated['date']);

            $inspection = Inspection::create([
                ...$validated,
                'report_id'        => $reportId,
                'photo'            => $photoPath,
                'device_category'  => $validated['device_category'] ?: 'other',
                'user_snipeit_id'  => $validated['user_id'] ?? null,
                'it_staff_id'      => $validated['it_staff_id'] ?? null,
                'checked_by_id'    => $validated['checked_by_id'] ?? null,
            ]);

            $note = AssetNoteFormatterService::formatSimpleNote(
                $inspection,
                action: "Inspection Created",
                recipient: $inspection->user
            );
            $this->logInspection('created', $inspection, $note);

            return redirect()->route('inspection.show', $inspection->id)
                ->with('success', 'Inspection berhasil dibuat.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal membuat Inspection: ' . $e->getMessage());
        }
    }

    public function show(Inspection $inspection): \Inertia\Response
    {
        $isCompleted   = (bool) $inspection->completed_at;
        $shareUrl      = null;
        $shareSignUrls = [];

        $shareUrl = $isCompleted ? null : \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'inspection.share', now()->addDays(7), ['inspection' => $inspection->id]
        );
        $shareUrl = $this->useCurrentRequestHost($shareUrl);

        if (!$isCompleted) {
            $shareSignUrls = [
                'it'      => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'it']),
                'checked' => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'checked']),
                'user'    => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'user']),
                'leader'  => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'leader']),
            ];
        }

        return Inertia::render('Inspection/Show', [
            'inspection'    => $inspection,
            'shareUrl'      => $shareUrl,
            'shareSignUrls' => $shareSignUrls,
        ]);
    }

    private function useCurrentRequestHost(?string $url): ?string
    {
        if (!$url) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['path'])) {
            return $url;
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return request()->getSchemeAndHttpHost() . $parts['path'] . $query;
    }

    public function edit(Inspection $inspection): \Inertia\Response
    {
        // Map stored IDs back to form field names the form expects
        $data = $inspection->toArray();
        $data['user_id']       = $inspection->user_snipeit_id;
        $data['it_staff_id']   = $inspection->it_staff_id;
        $data['checked_by_id'] = $inspection->checked_by_id;
        // Ensure date fields are plain date strings (not datetime)
        $data['date']         = $inspection->date ? \Carbon\Carbon::parse($inspection->date)->format('Y-m-d') : null;
        $data['checked_date'] = $inspection->checked_date ? \Carbon\Carbon::parse($inspection->checked_date)->format('Y-m-d') : null;

        return Inertia::render('Inspection/Edit', [
            'inspection'   => $data,
            'nextSequence' => $inspection->id,
        ]);
    }

    public function update(Request $request, Inspection $inspection): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'report_type'       => 'required|string|max:255',
            'company'           => 'required|string|max:255',
            'department'        => 'required|string|max:255',
            'dept_head'         => 'nullable|string|max:255',
            'it_staff'          => 'nullable|string|max:255',
            'user'              => 'required|string|max:255',
            'email'             => 'nullable|email|max:255',
            'date'              => 'required|date',
            'location'          => 'required|string|max:255',
            'device_category'   => 'nullable|string|max:50',
            'device_name'       => 'nullable|string|max:255',
            'asset_tag'         => 'nullable|string|max:255',
            'serial_number'     => 'nullable|string|max:255',
            'asset_snapshot'    => 'nullable|string',
            'snipeit_asset_id'  => 'nullable|integer',
            'checked_by'        => 'required|string|max:255',
            'checked_date'      => 'required|date',
            'issue_description' => 'required|string',
            'solution'          => 'required|string',
            'remarks'           => 'nullable|string',
            'photo'             => 'nullable|image|max:5120',
            'user_id'           => 'nullable|integer',
            'it_staff_id'       => 'nullable|integer',
            'checked_by_id'     => 'nullable|integer',
        ]);

        try {
            $photoPath = $this->handlePhotoUpload($request, $inspection->photo);

            $inspection->update([
                ...$validated,
                'photo'           => $photoPath,
                'user_snipeit_id' => $validated['user_id'] ?? $inspection->user_snipeit_id,
                'it_staff_id'     => $validated['it_staff_id'] ?? $inspection->it_staff_id,
                'checked_by_id'   => $validated['checked_by_id'] ?? $inspection->checked_by_id,
            ]);

            $note = AssetNoteFormatterService::formatSimpleNote(
                $inspection,
                action: "Inspection Updated",
                recipient: $inspection->user
            );
            $this->logInspection('updated', $inspection, $note);

            return redirect()->route('inspection.show', $inspection->id)
                ->with('success', 'Inspection berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal memperbarui Inspection: ' . $e->getMessage());
        }
    }

    public function destroy(Inspection $inspection): \Illuminate\Http\RedirectResponse
    {
        try {
            if ($inspection->photo) {
                Storage::disk('public')->delete($inspection->photo);
            }

            $reportId = $inspection->report_id;
            $note = AssetNoteFormatterService::formatSimpleNote(
                $inspection,
                action: "Inspection Deleted",
                recipient: auth()->user()->name
            );
            $this->logInspection('deleted', $inspection, $note);
            $inspection->delete();

            return redirect()->route('inspection.index')
                ->with('success', 'Inspection berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Gagal menghapus Inspection: ' . $e->getMessage());
        }
    }

    public function print(Inspection $inspection): mixed
    {
        // If completed PDF exists, serve it directly
        if ($inspection->completed_pdf_path
            && \Illuminate\Support\Facades\Storage::disk('public')->exists($inspection->completed_pdf_path)) {
            return response()->file(
                storage_path('app/public/' . $inspection->completed_pdf_path),
                [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . basename($inspection->completed_pdf_path) . '"',
                ]
            );
        }

        // If completed but PDF missing, redirect back with error
        if ($inspection->completed_at) {
            return redirect()->route('inspection.show', $inspection->id)
                ->with('error', 'PDF belum tersedia. Coba generate ulang dari halaman detail.');
        }

        return Inertia::render('Inspection/Print', [
            'inspection' => $inspection,
            'shareUrl'   => \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'inspection.share', now()->addDays(7), ['inspection' => $inspection->id]
            ),
        ]);
    }

    /**
     * Public shared view (signed URL, no auth required).
     */
    public function sharedShow(\Illuminate\Http\Request $request, Inspection $inspection): \Inertia\Response
    {
        if ($inspection->completed_at) {
            abort(410, 'Public link sudah tidak aktif.');
        }

        $shareSignUrls = [
            'it'      => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'it']),
            'checked' => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'checked']),
            'user'    => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'user']),
            'leader'  => \Illuminate\Support\Facades\URL::temporarySignedRoute('inspection.share.sign', now()->addDays(7), ['inspection' => $inspection->id, 'role' => 'leader']),
        ];

        return Inertia::render('Inspection/Share', [
            'inspection'    => $inspection,
            'sharedMode'    => true,
            'shareSignUrls' => $shareSignUrls,
        ]);
    }

    /**
     * Sign via public share link (signed URL, no auth required).
     */
    public function sharedSign(\Illuminate\Http\Request $request, Inspection $inspection, string $role): \Illuminate\Http\JsonResponse
    {
        return $this->sign($request, $inspection, $role);
    }

    // PDF Generation

    private function buildInspectionPdfData(Inspection $inspection): array
    {
        $formatDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('n/j/Y') : '-';

        $photo = null;
        if ($inspection->photo) {
            $path = storage_path('app/public/' . $inspection->photo);
            if (file_exists($path)) {
                $mime = mime_content_type($path) ?: 'image/jpeg';
                $photo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        $logo = null;
        $logoPath = public_path('form-logo.png');
        if (file_exists($logoPath)) {
            $logo = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        return [
            'reportId'          => $inspection->report_id,
            'location'          => $inspection->location ?? '-',
            'checkedBy'         => $inspection->checked_by ?? '-',
            'checkedDate'       => $formatDate($inspection->checked_date),
            'department'        => $inspection->department ?? '-',
            'userName'          => $inspection->user ?? '-',
            'email'             => $inspection->email ?? '-',
            'date'              => $formatDate($inspection->date),
            'deviceCategory'    => $inspection->device_category ?? 'other',
            'deviceName'        => $inspection->device_name ?? '-',
            'assetTag'          => $inspection->asset_tag ?? '-',
            'serialNumber'      => $inspection->serial_number ?? '-',
            'issueDescription'  => $inspection->issue_description ?? '-',
            'solution'          => $inspection->solution ?? '-',
            'remarks'           => $inspection->remarks ?? '',
            'itStaff'           => $inspection->it_staff ?? '',
            'deptHead'          => $inspection->dept_head ?? '-',
            'itSignature'       => $inspection->it_signature ?? null,
            'checkedSignature'  => $inspection->checked_signature ?? null,
            'userSignature'     => $inspection->user_signature ?? null,
            'leaderSignature'   => $inspection->leader_signature ?? null,
            'photo'             => $photo,
            'logo'              => $logo,
        ];
    }

    private function generateInspectionPdf(Inspection $inspection): string
    {
        if (app()->environment('testing')) {
            $path = 'inspection-pdfs/' . \Illuminate\Support\Str::slug($inspection->report_id) . '.pdf';
            Storage::disk('public')->put($path, '%PDF-1.4 test');
            return $path;
        }

        $viewData = $this->buildInspectionPdfData($inspection);
        $slug     = \Illuminate\Support\Str::slug($inspection->report_id, '-');
        $relative = 'inspection-pdfs/' . $slug . '.pdf';

        $browserPath = $this->getPdfBrowserPath();
        if (!$browserPath) {
            throw new \RuntimeException('Headless browser not found for PDF generation.');
        }

        $html    = view('inspection.pdf_final', $viewData)->render();
        $tempDir = storage_path('app/inspection-temp');
        if (!is_dir($tempDir)) mkdir($tempDir, 0777, true);

        $htmlPath    = $tempDir . DIRECTORY_SEPARATOR . \Illuminate\Support\Str::uuid() . '.html';
        $profilePath = $tempDir . DIRECTORY_SEPARATOR . 'profile-' . \Illuminate\Support\Str::uuid();
        file_put_contents($htmlPath, $html);
        if (!is_dir($profilePath)) mkdir($profilePath, 0777, true);

        $pdfAbsPath = storage_path('app/public/' . $relative);
        $pdfDir     = dirname($pdfAbsPath);
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

        $process = new \Symfony\Component\Process\Process([
            $browserPath,
            '--headless=new', '--disable-gpu', '--disable-crash-reporter',
            '--disable-breakpad', '--no-first-run', '--no-default-browser-check',
            '--disable-features=msEdgeCloudManagement,RendererCodeIntegrity',
            '--user-data-dir=' . $profilePath,
            '--allow-file-access-from-files', '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw', '--virtual-time-budget=12000',
            '--print-to-pdf=' . $pdfAbsPath,
            'file:///' . str_replace('\\', '/', $htmlPath),
        ]);
        $process->setTimeout(60);
        $process->run();
        @unlink($htmlPath);

        if (is_dir($profilePath)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($profilePath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $f) { $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath()); }
            @rmdir($profilePath);
        }

        if (!$process->isSuccessful() || !is_file($pdfAbsPath)) {
            throw new \RuntimeException('PDF generation failed. ' . trim($process->getErrorOutput()));
        }

        return $relative;
    }

    private function getPdfBrowserPath(): ?string
    {
        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }
        return null;
    }

    /**
     * Sign an inspection (it, checked, user, leader).
     */
    public function sign(Request $request, Inspection $inspection, string $role): \Illuminate\Http\JsonResponse
    {
        $allowed = ['it', 'checked', 'user', 'leader'];
        if (!in_array($role, $allowed)) {
            return response()->json(['message' => 'Invalid role.'], 422);
        }

        $request->validate(['signature' => 'required|string']);

        $field = match ($role) {
            'it'      => 'it_signature',
            'checked' => 'checked_signature',
            'user'    => 'user_signature',
            'leader'  => 'leader_signature',
        };

        $inspection->update([
            $field           => $request->input('signature'),
            'signature_date' => now()->toDateString(),
        ]);

        $roleLabel = ['it' => 'IT', 'checked' => 'Checked', 'user' => 'User', 'leader' => 'Leader/Head Dept'][$role] ?? $role;
        $note = AssetNoteFormatterService::formatSimpleNote(
            $inspection,
            action: "Signature Added — {$roleLabel}",
            recipient: $roleLabel
        );
        $this->logInspection('sign', $inspection, $note, ['role' => $role]);

        return response()->json(['message' => 'Tanda tangan berhasil disimpan.']);
    }

    /**
     * Clear a signature.
     */
    public function clearSign(Inspection $inspection, string $role): \Illuminate\Http\JsonResponse
    {
        $allowed = ['it', 'checked', 'user', 'leader'];
        if (!in_array($role, $allowed)) {
            return response()->json(['message' => 'Invalid role.'], 422);
        }

        if ($inspection->completed_at) {
            return response()->json(['message' => 'Inspection sudah selesai, tanda tangan tidak bisa dihapus.'], 422);
        }

        $field = match ($role) {
            'it'      => 'it_signature',
            'checked' => 'checked_signature',
            'user'    => 'user_signature',
            'leader'  => 'leader_signature',
        };

        $inspection->update([$field => null]);

        $roleLabel = ['it' => 'IT', 'checked' => 'Checked', 'user' => 'User', 'leader' => 'Leader/Head Dept'][$role] ?? $role;
        $note = AssetNoteFormatterService::formatSimpleNote(
            $inspection,
            action: "Signature Cleared — {$roleLabel}",
            recipient: $roleLabel
        );
        $this->logInspection('sign_cleared', $inspection, $note, ['role' => $role]);

        return response()->json(['message' => 'Tanda tangan berhasil dihapus.']);
    }

    /**
    * Complete the inspection document and apply its inventory event.
     */
    public function complete(Request $request, Inspection $inspection): \Illuminate\Http\RedirectResponse
    {
        if ($inspection->completed_at) {
            return redirect()->back()->with('error', 'Inspection sudah pernah diselesaikan.');
        }

        // Resolve Snipe-IT asset ID from snapshot or stored field
        $assetId = $inspection->snipeit_asset_id;
        if (!$assetId && $inspection->asset_snapshot) {
            try {
                $snap    = json_decode($inspection->asset_snapshot, true);
                $assetId = $snap['id'] ?? null;
            } catch (\Exception $e) {
                // ignore
            }
        }

        $syncLog    = [];
        $syncStatus = 'skipped';
        if ($assetId) {
            try {
                $snipe = app(\App\Services\SnipeItService::class);
                $assetType = $this->resolveInspectionAssetType($inspection);
                $note = "Inspection: {$inspection->report_id} | Issue: " . substr($inspection->issue_description ?? '', 0, 120);
                $syncLog[] = "Asset ID: {$assetId} | Type: {$assetType}";

                if ($assetType === 'hardware') {
                    if (!$this->isTotalDeadInspection($inspection)) {
                        $syncLog[] = 'Hardware inspection did not indicate total dead asset; no Snipe-IT status change required.';
                    } else {
                        $checkinResult = $snipe->checkinAsset('hardware', $assetId, [
                            'note' => "Checked in before marking broken via Inspection {$inspection->report_id}.",
                        ]);
                        $syncLog[] = "Checkin result: " . ($checkinResult['status'] ?? 'unknown');
                        if (($checkinResult['status'] ?? '') === 'error') {
                            throw new \Exception("Hardware checkin before broken update failed: " . json_encode($checkinResult['messages'] ?? []));
                        }

                        $brokenStatusId = $this->resolveBrokenStatusId($snipe);
                        $updateResult = $snipe->updateRecord('hardware', $assetId, [
                            'status_id' => $brokenStatusId,
                            'notes'     => "Marked broken via Inspection {$inspection->report_id}.",
                        ]);
                        $syncLog[] = "Status update result: " . ($updateResult['status'] ?? 'unknown');
                        if (($updateResult['status'] ?? '') === 'error') {
                            throw new \Exception("Status update failed: " . json_encode($updateResult['messages'] ?? []));
                        }
                        $syncStatus = 'success';
                        $syncLog[]  = 'Hardware checked in and status set to Broken.';
                    }
                } elseif ($assetType === 'components' || $assetType === 'accessories') {
                    $checkoutId = $this->resolveAssignedCheckoutId($snipe, $inspection, $assetType, $assetId);
                    if (!$checkoutId) {
                        throw new \Exception("No active {$assetType} checkout found for this user and item.");
                    }

                    $checkinResult = $snipe->checkinAsset($assetType, $checkoutId, [
                        'note' => "Checked in before quantity reduction via Inspection {$inspection->report_id}.",
                    ]);
                    $syncLog[] = "Checkin result: " . ($checkinResult['status'] ?? 'unknown');
                    if (($checkinResult['status'] ?? '') === 'error') {
                        throw new \Exception("{$assetType} checkin failed: " . json_encode($checkinResult['messages'] ?? []));
                    }

                    $current = $snipe->request("{$assetType}/{$assetId}");
                    $qtyField = array_key_exists('qty', $current) ? 'qty' : 'total_qty';
                    $currentQty = (int) ($current[$qtyField] ?? 0);
                    if ($currentQty < 1) {
                        throw new \Exception("{$assetType} quantity is already zero; cannot reduce inventory.");
                    }

                    $updateResult = $snipe->updateRecord($assetType, $assetId, [
                        $qtyField => $currentQty - 1,
                        'notes' => "Quantity reduced by 1 via Inspection {$inspection->report_id}.",
                    ]);
                    $syncLog[] = "Quantity update result: " . ($updateResult['status'] ?? 'unknown');
                    if (($updateResult['status'] ?? '') === 'error') {
                        throw new \Exception("Quantity reduction failed: " . json_encode($updateResult['messages'] ?? []));
                    }

                    $syncStatus = 'success';
                    $syncLog[] = "{$assetType} checked in and quantity reduced by 1.";
                } elseif ($assetType === 'other' || $assetType === 'maintenance') {
                    $maintenancePayload = [
                        'name' => 'Inspection ' . $inspection->report_id . ' - ' . ($inspection->device_name ?: 'Item'),
                        'asset_id' => $assetId,
                        'asset_maintenance_type' => 'Inspection',
                        'start_date' => $inspection->date ? $inspection->date->toDateString() : now()->toDateString(),
                        'notes' => $note . ' | Solution: ' . ($inspection->solution ?? 'No additional notes'),
                    ];
                    $result = $snipe->createRecord('maintenances', $maintenancePayload);
                    $syncLog[] = "Maintenance create result: " . ($result['status'] ?? 'unknown');
                    if (($result['status'] ?? '') === 'error') {
                        throw new \Exception("Maintenance creation failed: " . json_encode($result['messages'] ?? []));
                    }
                    $syncStatus = 'success';
                    $syncLog[]  = 'Inspection logged to Snipe-IT maintenance without checkin or status change.';
                } else {
                    $syncLog[] = 'Resource does not require Snipe-IT status or checkin mutation for this inspection flow.';
                }

                if ($assetType === 'hardware') {
                    $snipe->flushCacheForAsset('assets', $assetId);
                } elseif ($assetType === 'components' || $assetType === 'accessories' || $assetType === 'other' || $assetType === 'maintenance') {
                    $snipe->flushCacheForAsset('component', $assetId);
                }

            } catch (\Exception $e) {
                $syncStatus = 'failed';
                $syncLog[]  = "Error: " . $e->getMessage();
                \Log::error('Inspection Snipe-IT sync failed', [
                    'inspection_id' => $inspection->id,
                    'asset_id'      => $assetId,
                    'asset_type'    => $assetType ?? null,
                    'error'         => $e->getMessage(),
                ]);
            }
        } else {
            $syncLog[] = 'No Snipe-IT asset ID found, skipping sync.';
        }

        // Mark inspection as completed
        $inspection->update([
            'completed_at'        => $syncStatus === 'failed' ? null : now(),
            'snipeit_synced_at'   => $syncStatus === 'success' ? now() : null,
            'snipeit_sync_status' => $syncStatus,
            'snipeit_sync_log'    => implode("\n", $syncLog),
        ]);

        // Generate PDF
        try {
            $pdfPath = $this->generateInspectionPdf($inspection);
            $inspection->update(['completed_pdf_path' => $pdfPath]);

            // Upload PDF to Snipe-IT asset if we have an asset ID
            if ($assetId && $syncStatus === 'success') {
                try {
                    $snipe = app(\App\Services\SnipeItService::class);
                    $this->uploadPdfToSnipeit($snipe, $inspection, $assetId, $pdfPath);
                    $syncLog[] = "PDF uploaded to Snipe-IT asset #{$assetId}.";
                } catch (\Exception $e) {
                    \Log::warning('Inspection PDF upload to Snipe-IT failed', [
                        'id'    => $inspection->id,
                        'error' => $e->getMessage(),
                    ]);
                    $syncLog[] = "PDF upload to Snipe-IT failed: " . $e->getMessage();
                }
                $inspection->update(['snipeit_sync_log' => implode("\n", $syncLog)]);
            } elseif ($assetId && $syncStatus === 'failed') {
                $syncLog[] = "PDF upload skipped because Snipe-IT inventory sync failed.";
                $inspection->update(['snipeit_sync_log' => implode("\n", $syncLog)]);
            }
        } catch (\Exception $e) {
            \Log::warning('Inspection PDF generation failed', ['id' => $inspection->id, 'error' => $e->getMessage()]);
        }

        $message = $syncStatus === 'success'
            ? ($this->isTotalDeadInspection($inspection) && $this->resolveInspectionAssetType($inspection) === 'hardware'
                ? 'Inspection selesai. Status asset diubah ke Broken di Snipe-IT.'
                : 'Inspection selesai. Catatan maintenance berhasil ditambahkan ke Snipe-IT.')
            : ($syncStatus === 'failed'
                ? 'Inspection tersimpan, tetapi belum selesai karena sync ke Snipe-IT gagal: ' . end($syncLog)
                : 'Inspection selesai. Tidak ada perubahan Snipe-IT yang diperlukan untuk item ini.');

        $note = AssetNoteFormatterService::formatSimpleNote(
            $inspection,
            action: "Inspection {$syncStatus}",
            recipient: "Snipe-IT"
        );
        $this->logInspection($syncStatus === 'failed' ? 'sync_failed' : 'completed', $inspection, $note, [
            'sync_status' => $syncStatus,
            'asset_id'    => $assetId,
            'sync_log'    => implode(' | ', $syncLog),
        ]);

        return redirect()->route('inspection.show', $inspection->id)
            ->with($syncStatus === 'failed' ? 'warning' : 'success', $message);
    }

    /**
     * Upload the completed PDF to the Snipe-IT asset's files AND the user's files.
     */
    private function uploadPdfToSnipeit(\App\Services\SnipeItService $snipe, Inspection $inspection, int $assetId, string $pdfPath): void
    {
        $absPath = storage_path('app/public/' . $pdfPath);
        if (!file_exists($absPath)) {
            throw new \RuntimeException("PDF file not found at: {$absPath}");
        }

        $content  = file_get_contents($absPath);
        $filename = basename($pdfPath);
        $notes    = "Inspection Report: {$inspection->report_id}";

        // Determine resource type from snapshot
        $snapshot     = $inspection->asset_snapshot ? json_decode($inspection->asset_snapshot, true) : [];
        $snapshotType = strtolower($snapshot['asset_type'] ?? '');

        if (str_contains($snapshotType, 'accessor')) {
            $resource = 'accessories';
        } elseif (str_contains($snapshotType, 'component')) {
            $resource = 'components';
        } else {
            $resource = 'hardware';
        }

        // 1. Upload to asset
        $result = $snipe->uploadFile($resource, $assetId, $content, $filename, $notes);
        if (isset($result['status']) && $result['status'] === 'error') {
            throw new \RuntimeException('Snipe-IT asset upload error: ' . json_encode($result['messages'] ?? $result));
        }

        // 2. Upload to user (if user_snipeit_id is stored)
        $userId = $inspection->user_snipeit_id;
        if ($userId) {
            $userResult = $snipe->uploadFile('users', (int) $userId, $content, $filename, $notes);
            if (isset($userResult['status']) && $userResult['status'] === 'error') {
                // Log but don't throw — asset upload already succeeded
                \Log::warning('Inspection PDF upload to user failed', [
                    'inspection_id' => $inspection->id,
                    'user_id'       => $userId,
                    'error'         => json_encode($userResult['messages'] ?? $userResult),
                ]);
            }
        }
    }

    /**
     * Re-upload the PDF for an already-completed inspection (e.g. for fixing old records).
     */
    public function reuploadPdf(Inspection $inspection): \Illuminate\Http\RedirectResponse
    {
        if (!$inspection->completed_pdf_path) {
            return redirect()->route('inspection.show', $inspection->id)
                ->with('error', 'PDF belum tersedia untuk inspection ini.');
        }

        $assetId = $inspection->snipeit_asset_id;
        if (!$assetId && $inspection->asset_snapshot) {
            try {
                $snap    = json_decode($inspection->asset_snapshot, true);
                $assetId = $snap['id'] ?? null;
            } catch (\Exception $e) { /* ignore */ }
        }

        if (!$assetId) {
            return redirect()->route('inspection.show', $inspection->id)
                ->with('error', 'Tidak ada asset Snipe-IT yang terhubung ke inspection ini.');
        }

        try {
            $snipe = app(\App\Services\SnipeItService::class);
            $this->uploadPdfToSnipeit($snipe, $inspection, $assetId, $inspection->completed_pdf_path);

            return redirect()->route('inspection.show', $inspection->id)
                ->with('success', "PDF berhasil diupload ke Snipe-IT asset #{$assetId}.");
        } catch (\Exception $e) {
            \Log::error('Inspection PDF re-upload failed', [
                'inspection_id' => $inspection->id,
                'error'         => $e->getMessage(),
            ]);
            return redirect()->route('inspection.show', $inspection->id)
                ->with('error', 'Gagal upload PDF ke Snipe-IT: ' . $e->getMessage());
        }
    }

    /**
     * Determine the Snipe-IT category relevant for inspection handling.
     */
    private function resolveInspectionAssetType(Inspection $inspection): string
    {
        $snapshot = $inspection->asset_snapshot ? json_decode($inspection->asset_snapshot, true) : [];
        $type = strtolower((string) ($snapshot['asset_type'] ?? ''));

        if (str_contains($type, 'accessor')) {
            return 'accessories';
        }

        if (str_contains($type, 'component') || str_contains($type, 'part') || str_contains($type, 'spare')) {
            return 'components';
        }

        $reportType = strtolower((string) ($inspection->report_type ?? ''));

        if (str_contains($reportType, 'hardware') || str_contains($reportType, 'asset')) {
            return 'hardware';
        }

        if (str_contains($reportType, 'part') || str_contains($reportType, 'component')
            || str_contains($reportType, 'accessor') || str_contains($reportType, 'other')) {
            return 'maintenance';
        }

        $type = strtolower((string) ($inspection->device_category ?? ''));

        if (str_contains($type, 'accessor')) {
            return 'accessories';
        }

        if (str_contains($type, 'component') || str_contains($type, 'part') || str_contains($type, 'spare')) {
            return 'components';
        }

        if ($type === '' || $type === 'other' || $type === 'license' || $type === 'consumable') {
            return 'other';
        }

        return 'hardware';
    }

    private function resolveAssignedCheckoutId(
        \App\Services\SnipeItService $snipe,
        Inspection $inspection,
        string $assetType,
        int $assetId,
    ): ?int {
        if (!$inspection->user_snipeit_id) {
            return null;
        }

        $endpoint = $assetType === 'accessories'
            ? "accessories/{$assetId}/checkedout"
            : "components/{$assetId}/assets";
        $response = $snipe->request(
            $endpoint,
            ['limit' => 50],
            true,
        );
        $rows = $response['rows'] ?? [];

        foreach ($rows as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $assignedTo = $row['assigned_to'] ?? [];
            $assignedId = (int) (is_array($assignedTo) ? ($assignedTo['id'] ?? 0) : $assignedTo);
            if ($rowId > 0 && $assignedId === (int) $inspection->user_snipeit_id) {
                return $rowId;
            }
        }

        return null;
    }

    /**
     * Detect whether an inspection indicates the asset is completely dead.
     */
    private function isTotalDeadInspection(Inspection $inspection): bool
    {
        $haystack = strtolower(implode(' ', [
            (string) ($inspection->report_type ?? ''),
            (string) ($inspection->issue_description ?? ''),
            (string) ($inspection->solution ?? ''),
            (string) ($inspection->remarks ?? ''),
            (string) ($inspection->device_name ?? ''),
        ]));

        foreach (['mati total', 'asset mati', 'tidak bisa dinyalakan', 'tidak menyala', 'dead', 'down', 'totally dead', 'rusak', 'damage', 'bad sector', 'aus'] as $term) {
            if (str_contains($haystack, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the Snipe-IT status ID for "Broken" / "Out for Repair".
     */
    private function resolveBrokenStatusId(\App\Services\SnipeItService $snipe): int
    {
        try {
            $statuses = $snipe->fetchRows('statuslabels');
            $searchTerms = ['Broken', 'Out for Repair', 'Mati', 'Rusak'];

            // Exact name match first
            foreach ($searchTerms as $term) {
                foreach ($statuses as $s) {
                    if (strcasecmp($s['name'], $term) === 0) {
                        return (int) $s['id'];
                    }
                }
            }

            // Fallback: archived type
            foreach ($statuses as $s) {
                if (strtolower($s['status_type'] ?? '') === 'archived') {
                    return (int) $s['id'];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Could not resolve broken status ID: ' . $e->getMessage());
        }

        return 4; // Common default for "Out for Repair" in Snipe-IT
    }
}
