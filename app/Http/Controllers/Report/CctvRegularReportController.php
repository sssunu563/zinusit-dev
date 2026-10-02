<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\CctvDevice;
use App\Models\CctvRegularReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CctvRegularReportController extends Controller
{
    /**
     * Get paginated reports list with filter options.
     */
    public function data(Request $request): JsonResponse
    {
        $year = $request->input('year');
        $week = $request->input('week');
        $location = $request->input('location');
        $status = $request->input('status');
        $search = trim((string) $request->input('search', ''));

        $query = CctvRegularReport::with('creator:id,name,email')
            ->when($year, fn($q) => $q->where('year', $year))
            ->when($week, fn($q) => $q->where('week_number', $week))
            ->when($location, fn($q) => $q->where('location', $location))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('doc_no', 'like', "%{$search}%")
                        ->orWhere('checked_by', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
                });
            })
            ->latest('checked_date')
            ->latest('id');

        $reports = $query->paginate(15);

        // Calculate simple stats
        $stats = [
            'total'      => CctvRegularReport::count(),
            'this_year'  => CctvRegularReport::where('year', date('Y'))->count(),
            'latest_doc' => CctvRegularReport::latest('id')->value('doc_no'),
        ];

        return response()->json([
            'reports' => $reports,
            'stats'   => $stats,
        ]);
    }

    /**
     * Get a single report for viewing or editing.
     */
    public function show(int $id): JsonResponse
    {
        $report = CctvRegularReport::with('creator:id,name')->findOrFail($id);
        return response()->json(['report' => $report]);
    }

    /**
     * Get metadata helpers (NVR devices list, locations, next doc_no).
     */
    public function meta(Request $request): JsonResponse
    {
        $now = Carbon::now();
        $currentYear = (int) $now->format('Y');
        $currentWeek = (int) $now->isoWeek();

        // Optional support for caller-provided year/week pair so the date can be
        // derived consistently from the selected ISO week in the UI.
        if ($request->filled('year')) {
            $currentYear = (int) $request->input('year');
        }

        if ($request->filled('week')) {
            $currentWeek = (int) $request->input('week');
        }

        // Fetch NVR devices from CctvDevice
        $nvrs = CctvDevice::where('device_type', 'NVR')
            ->where('is_active', true)
            ->select('id', 'device_name', 'ip_address', 'location', 'site')
            ->orderBy('device_name')
            ->get();

        $defaultDocNo = CctvRegularReport::generateDocNo('ZGI BGR F1', $currentYear, $currentWeek);

        // Align the default checked_date with the chosen year/week pair by
        // returning the Monday of that ISO week, not the current calendar day.
        $defaultDate = Carbon::now()->setISODate($currentYear, $currentWeek, 1)->format('Y-m-d');

        $locations = [
            'ZGI BGR F1',
            'ZGI KRW F2',
            'ZDI TGR F3',
        ];

        return response()->json([
            'current_year'   => $currentYear,
            'current_week'   => $currentWeek,
            'default_date'   => $defaultDate,
            'default_doc_no' => $defaultDocNo,
            'nvrs'           => $nvrs,
            'locations'      => $locations,
            'current_user'   => auth()->user()?->name ?? 'IT Officer',
        ]);
    }

    /**
     * Store a newly created regular report.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location'     => 'required|string|max:100',
            'checked_by'   => 'required|string|max:150',
            'checked_date' => 'required|date',
            'week_number'  => 'required|integer|min:1|max:53',
            'year'         => 'required|integer|min:2020|max:2099',
            'doc_no'       => 'nullable|string|max:100',
        ]);

        $date = Carbon::parse($validated['checked_date']);
        $year = (int) ($validated['year'] ?? $date->year);
        $week = (int) ($validated['week_number'] ?? $date->isoWeek());

        $docNo = ($validated['doc_no'] ?? null) ?: CctvRegularReport::generateDocNo($validated['location'], $year, $week);

        // Prevent duplicate doc_no
        if (CctvRegularReport::where('doc_no', $docNo)->exists()) {
            $docNo .= '-' . uniqid();
        }

        // Process Dynamic Areas Data
        $areasData = $this->decodeJsonField($request->input('areas_data'));
        if (empty($areasData)) {
            // Fallback to legacy fields if areas_data not supplied
            $legacyLoading = $this->decodeJsonField($request->input('loading_area_data'));
            $legacyLoading['name'] = 'Loading Area';
            $legacyLoading['screenshots'] = $this->handleUploadedFiles($request, 'loading_screenshots', $legacyLoading['screenshots'] ?? []);

            $legacyBeacukai = $this->decodeJsonField($request->input('beacukai_data'));
            $legacyBeacukai['name'] = 'Beacukai CCTV';
            $legacyBeacukai['screenshots'] = $this->handleUploadedFiles($request, 'beacukai_screenshots', $legacyBeacukai['screenshots'] ?? []);

            $areasData = [$legacyLoading, $legacyBeacukai];
        } else {
            // Process uploaded screenshots for each area
            foreach ($areasData as $idx => &$area) {
                $areaScreenshotsKey = "area_{$idx}_screenshots";
                if (!$request->hasFile($areaScreenshotsKey)) {
                    $areaScreenshotsKey = "area_screenshots_{$idx}";
                }
                $existing = $area['screenshots'] ?? [];
                $newScreenshots = $this->handleUploadedFiles($request, $areaScreenshotsKey);
                $area['screenshots'] = array_merge($existing, $newScreenshots);

                // Auto-calculate retention days if both dates are present
                if (!empty($validated['checked_date']) && !empty($area['last_record_date'])) {
                    $cDate = Carbon::parse($validated['checked_date'])->startOfDay();
                    $rDate = Carbon::parse($area['last_record_date'])->startOfDay();
                    $area['retention_days'] = max(0, (int) $rDate->diffInDays($cDate));
                }
            }
            unset($area);
        }

        // Process Maintenance Data with per-item photos
        $maintenanceData = $this->decodeJsonField($request->input('maintenance_data'));
        if (isset($maintenanceData['items']) && is_array($maintenanceData['items'])) {
            foreach ($maintenanceData['items'] as $mIdx => &$mItem) {
                $beforeKey = "maintenance_{$mIdx}_before_photo";
                if ($request->hasFile($beforeKey)) {
                    $mItem['before_photo'] = '/storage/' . $request->file($beforeKey)->store('cctv-regular-reports', 'public');
                }
                $afterKey = "maintenance_{$mIdx}_after_photo";
                if ($request->hasFile($afterKey)) {
                    $mItem['after_photo'] = '/storage/' . $request->file($afterKey)->store('cctv-regular-reports', 'public');
                }
            }
            unset($mItem);
        }

        // Legacy top-level maintenance photos fallback
        if ($request->hasFile('maintenance_before_photo')) {
            $maintenanceData['before_photo'] = '/storage/' . $request->file('maintenance_before_photo')->store('cctv-regular-reports', 'public');
        }
        if ($request->hasFile('maintenance_after_photo')) {
            $maintenanceData['after_photo'] = '/storage/' . $request->file('maintenance_after_photo')->store('cctv-regular-reports', 'public');
        }

        $report = CctvRegularReport::create([
            'doc_no'                 => $docNo,
            'location'               => $validated['location'],
            'checked_by'             => $validated['checked_by'],
            'checked_date'           => $validated['checked_date'],
            'week_number'            => $week,
            'year'                   => $year,
            'areas_data'             => $areasData,
            'loading_area_data'      => $areasData[0] ?? null,
            'beacukai_data'          => $areasData[1] ?? null,
            'maintenance_data'       => $maintenanceData,
            'signature_dept1_name'   => $request->input('signature_dept1_name', 'IT'),
            'signature_dept1_signer' => $request->input('signature_dept1_signer', $validated['checked_by']),
            'signature_dept1_image'  => $request->input('signature_dept1_image'),
            'signature_dept1_at'     => $request->input('signature_dept1_image') ? now() : null,
            'signature_dept2_name'   => $request->input('signature_dept2_name', 'Exim'),
            'signature_dept2_signer' => $request->input('signature_dept2_signer'),
            'signature_dept2_image'  => $request->input('signature_dept2_image'),
            'signature_dept2_at'     => $request->input('signature_dept2_image') ? now() : null,
            'status'                 => $request->input('status', 'draft'),
            'created_by'             => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Laporan CCTV Regular Check berhasil disimpan.',
            'report'  => $report,
        ]);
    }

    /**
     * Update an existing regular report.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $report = CctvRegularReport::findOrFail($id);

        if ($report->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan telah berstatus Completed dan terkunci, tidak dapat diubah kembali.',
            ], 422);
        }

        $validated = $request->validate([
            'location'     => 'required|string|max:100',
            'checked_by'   => 'required|string|max:150',
            'checked_date' => 'required|date',
            'week_number'  => 'required|integer|min:1|max:53',
            'year'         => 'required|integer|min:2020|max:2099',
            'doc_no'       => 'nullable|string|max:100',
        ]);

        // Process Dynamic Areas Data
        $areasData = $this->decodeJsonField($request->input('areas_data'));
        if (empty($areasData)) {
            // Fallback to legacy fields
            $loadingAreaData = $this->decodeJsonField($request->input('loading_area_data'));
            $existingLoadingScreenshots = $loadingAreaData['screenshots'] ?? ($report->loading_area_data['screenshots'] ?? []);
            $newLoadingScreenshots = $this->handleUploadedFiles($request, 'loading_screenshots');
            $loadingAreaData['screenshots'] = array_merge($existingLoadingScreenshots, $newLoadingScreenshots);

            $beacukaiData = $this->decodeJsonField($request->input('beacukai_data'));
            $existingBeacukaiScreenshots = $beacukaiData['screenshots'] ?? ($report->beacukai_data['screenshots'] ?? []);
            $newBeacukaiScreenshots = $this->handleUploadedFiles($request, 'beacukai_screenshots');
            $beacukaiData['screenshots'] = array_merge($existingBeacukaiScreenshots, $newBeacukaiScreenshots);

            $areasData = [$loadingAreaData, $beacukaiData];
        } else {
            // Process uploaded screenshots for each area
            foreach ($areasData as $idx => &$area) {
                $areaScreenshotsKey = "area_{$idx}_screenshots";
                if (!$request->hasFile($areaScreenshotsKey)) {
                    $areaScreenshotsKey = "area_screenshots_{$idx}";
                }
                $existing = $area['screenshots'] ?? [];
                $newScreenshots = $this->handleUploadedFiles($request, $areaScreenshotsKey);
                $area['screenshots'] = array_merge($existing, $newScreenshots);

                // Auto-calculate retention days if dates are present
                if (!empty($validated['checked_date']) && !empty($area['last_record_date'])) {
                    $cDate = Carbon::parse($validated['checked_date'])->startOfDay();
                    $rDate = Carbon::parse($area['last_record_date'])->startOfDay();
                    $area['retention_days'] = max(0, (int) $rDate->diffInDays($cDate));
                }
            }
            unset($area);
        }

        // Process Maintenance Data with per-item photos
        $maintenanceData = $this->decodeJsonField($request->input('maintenance_data'));
        if (isset($maintenanceData['items']) && is_array($maintenanceData['items'])) {
            $existingItems = $report->maintenance_data['items'] ?? [];
            foreach ($maintenanceData['items'] as $mIdx => &$mItem) {
                $beforeKey = "maintenance_{$mIdx}_before_photo";
                if ($request->hasFile($beforeKey)) {
                    $mItem['before_photo'] = '/storage/' . $request->file($beforeKey)->store('cctv-regular-reports', 'public');
                } else {
                    $mItem['before_photo'] = $mItem['before_photo'] ?? ($existingItems[$mIdx]['before_photo'] ?? null);
                }

                $afterKey = "maintenance_{$mIdx}_after_photo";
                if ($request->hasFile($afterKey)) {
                    $mItem['after_photo'] = '/storage/' . $request->file($afterKey)->store('cctv-regular-reports', 'public');
                } else {
                    $mItem['after_photo'] = $mItem['after_photo'] ?? ($existingItems[$mIdx]['after_photo'] ?? null);
                }
            }
            unset($mItem);
        }

        // Top-level legacy maintenance photos fallback
        if ($request->hasFile('maintenance_before_photo')) {
            $maintenanceData['before_photo'] = '/storage/' . $request->file('maintenance_before_photo')->store('cctv-regular-reports', 'public');
        } else {
            $maintenanceData['before_photo'] = $maintenanceData['before_photo'] ?? ($report->maintenance_data['before_photo'] ?? null);
        }

        if ($request->hasFile('maintenance_after_photo')) {
            $maintenanceData['after_photo'] = '/storage/' . $request->file('maintenance_after_photo')->store('cctv-regular-reports', 'public');
        } else {
            $maintenanceData['after_photo'] = $maintenanceData['after_photo'] ?? ($report->maintenance_data['after_photo'] ?? null);
        }

        $report->update([
            'location'               => $validated['location'],
            'checked_by'             => $validated['checked_by'],
            'checked_date'           => $validated['checked_date'],
            'week_number'            => $validated['week_number'],
            'year'                   => $validated['year'],
            'doc_no'                 => $request->input('doc_no') ?: $report->doc_no,
            'areas_data'             => $areasData,
            'loading_area_data'      => $areasData[0] ?? $report->loading_area_data,
            'beacukai_data'          => $areasData[1] ?? $report->beacukai_data,
            'maintenance_data'       => $maintenanceData,
            'signature_dept1_name'   => $request->input('signature_dept1_name', $report->signature_dept1_name),
            'signature_dept1_signer' => $request->input('signature_dept1_signer', $report->signature_dept1_signer),
            'signature_dept1_image'  => $request->input('signature_dept1_image') ?: $report->signature_dept1_image,
            'signature_dept1_at'     => $request->input('signature_dept1_image') ? now() : $report->signature_dept1_at,
            'signature_dept2_name'   => $request->input('signature_dept2_name', $report->signature_dept2_name),
            'signature_dept2_signer' => $request->input('signature_dept2_signer', $report->signature_dept2_signer),
            'signature_dept2_image'  => $request->input('signature_dept2_image') ?: $report->signature_dept2_image,
            'signature_dept2_at'     => $request->input('signature_dept2_image') ? now() : $report->signature_dept2_at,
            'status'                 => $request->input('status', $report->status ?: 'draft'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Laporan CCTV Regular Check berhasil diperbarui.',
            'report'  => $report,
        ]);
    }

    /**
     * Mark a report as completed (locked from further edits).
     */
    public function complete(int $id): JsonResponse
    {
        $report = CctvRegularReport::findOrFail($id);
        $report->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil diselesaikan dan terkunci.',
            'report'  => $report,
        ]);
    }

    /**
     * Delete report and stored media files.
     */
    public function destroy(int $id): JsonResponse
    {
        $report = CctvRegularReport::findOrFail($id);

        if ($report->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Laporan yang telah Completed dan terkunci tidak dapat dihapus.',
            ], 422);
        }

        // Collect all uploaded files from dynamic areas and maintenance
        $allFiles = [];
        foreach ($report->resolved_areas as $area) {
            if (!empty($area['screenshots']) && is_array($area['screenshots'])) {
                $allFiles = array_merge($allFiles, $area['screenshots']);
            }
        }

        if (!empty($report->maintenance_data['items']) && is_array($report->maintenance_data['items'])) {
            foreach ($report->maintenance_data['items'] as $item) {
                if (!empty($item['before_photo'])) $allFiles[] = $item['before_photo'];
                if (!empty($item['after_photo'])) $allFiles[] = $item['after_photo'];
            }
        }

        if (!empty($report->maintenance_data['before_photo'])) $allFiles[] = $report->maintenance_data['before_photo'];
        if (!empty($report->maintenance_data['after_photo'])) $allFiles[] = $report->maintenance_data['after_photo'];

        foreach (array_unique(array_filter($allFiles)) as $fileUrl) {
            $path = str_replace('/storage/', '', $fileUrl);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dihapus.',
        ]);
    }

    /**
     * High-fidelity Print / PDF Blade view identical to physical 3-page template.
     */
    public function printView(int $id): View
    {
        $report = CctvRegularReport::findOrFail($id);

        return view('report.cctv_regular_report', [
            'report' => $report,
        ]);
    }

    /**
     * Helper to decode JSON string if needed.
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Handle array of uploaded files.
     */
    private function handleUploadedFiles(Request $request, string $inputKey, array $default = []): array
    {
        $storedPaths = [];

        if ($request->hasFile($inputKey)) {
            $files = $request->file($inputKey);
            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                if ($file->isValid()) {
                    $path = $file->store('cctv-regular-reports', 'public');
                    $storedPaths[] = '/storage/' . $path;
                }
            }
        }

        return array_merge($default, $storedPaths);
    }
}
