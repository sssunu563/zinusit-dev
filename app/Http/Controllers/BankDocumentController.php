<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Models\Stb;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->resolveFilters($request);
        $stats = $this->calculateStats();

        $documents = $this->fetchDocuments($filters);

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $total = $documents->count();

        $paginatedItems = $documents->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('BankDocuments/Index', [
            'documents' => [
                'data'  => $paginator->items(),
                'links' => $paginator->linkCollection()->toArray(),
                'total' => $paginator->total(),
                'from'  => $paginator->firstItem(),
                'to'    => $paginator->lastItem(),
            ],
            'filters' => $filters,
            'stats'   => $stats,
            'document_types' => [
                ['key' => 'stb', 'label' => 'Dokumen STB (Serah Terima)'],
                ['key' => 'peminjaman', 'label' => 'Dokumen Peminjaman'],
                ['key' => 'inspection', 'label' => 'Laporan Inspeksi'],
            ],
            'statuses' => [
                ['key' => 'completed', 'label' => 'Selesai / Ada PDF'],
                ['key' => 'pending', 'label' => 'Dalam Proses / Draft'],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $documents = $this->fetchDocuments($filters);
        $filename = 'bank-dokumen-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($documents): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Nomor Dokumen',
                'Jenis Dokumen',
                'Penerima / Pemilik',
                'Departemen',
                'Perusahaan',
                'Status',
                'Tanggal Dokumen',
                'Memiliki PDF',
                'URL Unduh PDF',
            ]);

            foreach ($documents as $doc) {
                fputcsv($handle, [
                    $doc['doc_no'],
                    $doc['doc_type_label'],
                    $doc['user_name'],
                    $doc['user_dept'] ?? '-',
                    $doc['user_company'] ?? '-',
                    $doc['status_label'],
                    $doc['created_at'],
                    $doc['has_pdf'] ? 'Ya' : 'Tidak',
                    $doc['pdf_url'] ? url($doc['pdf_url']) : '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function fetchDocuments(array $filters): Collection
    {
        $all = collect();

        // 1. Fetch STB & Peminjaman from `stbs` table
        $stbQuery = Stb::with('items')
            ->when($filters['from_date'], fn($q) => $q->whereDate('created_at', '>=', $filters['from_date']))
            ->when($filters['to_date'], fn($q) => $q->whereDate('created_at', '<=', $filters['to_date']))
            ->when($filters['filter_status'] === 'completed', fn($q) => $q->whereNotNull('completed_pdf_path'))
            ->when($filters['filter_status'] === 'pending', fn($q) => $q->whereNull('completed_pdf_path'))
            ->latest('created_at');

        if ($filters['filter_type'] === 'stb') {
            $stbQuery->where('document_type', '!=', 'loan');
        } elseif ($filters['filter_type'] === 'peminjaman') {
            $stbQuery->where('document_type', 'loan');
        } elseif ($filters['filter_type'] === 'inspection') {
            $stbQuery->whereRaw('1 = 0'); // Skip STB
        }

        $stbs = $stbQuery->get()->map(function (Stb $stb) {
            $isLoan = $stb->document_type === 'loan';
            $docNo = $this->resolveStbDocNo($stb);
            $hasPdf = !empty($stb->completed_pdf_path) && Storage::disk('public')->exists($stb->completed_pdf_path);

            return [
                'id'             => $stb->id,
                'doc_no'         => $docNo,
                'raw_id'         => $stb->id,
                'doc_type'       => $isLoan ? 'peminjaman' : 'stb',
                'doc_type_label' => $isLoan ? 'Peminjaman' : 'Dokumen STB',
                'sub_type'       => $stb->movement_type === 'return' ? 'Pengembalian' : 'Penyerahan',
                'user_name'      => $stb->user_name ?: '-',
                'user_dept'      => $stb->user_dept ?: '-',
                'user_company'   => $stb->user_company ?: '-',
                'status'         => $stb->cancelled_at ? 'cancelled' : ($hasPdf || $stb->is_completed ? 'completed' : 'in_progress'),
                'status_label'   => $stb->cancelled_at ? 'Dibatalkan' : ($hasPdf || $stb->is_completed ? 'Selesai' : 'Dalam Proses'),
                'has_pdf'        => $hasPdf,
                'pdf_url'        => $hasPdf ? '/storage/' . ltrim($stb->completed_pdf_path, '/') : null,
                'print_url'      => route($isLoan ? 'peminjaman.print' : 'stb.print', $stb->id),
                'view_url'       => route($isLoan ? 'peminjaman.show' : 'stb.show', $stb->id),
                'items_count'    => $stb->items ? $stb->items->count() : 0,
                'remark'         => $stb->remark,
                'created_at'     => $stb->created_at?->format('Y-m-d H:i:s') ?? '-',
                'created_timestamp' => $stb->created_at?->timestamp ?? 0,
            ];
        });

        $all = $all->concat($stbs);

        // 2. Fetch Inspections from `inspections` table
        if (in_array($filters['filter_type'], ['', 'inspection'], true)) {
            $inspectionQuery = Inspection::query()
                ->when($filters['from_date'], fn($q) => $q->whereDate('created_at', '>=', $filters['from_date']))
                ->when($filters['to_date'], fn($q) => $q->whereDate('created_at', '<=', $filters['to_date']))
                ->when($filters['filter_status'] === 'completed', fn($q) => $q->whereNotNull('completed_pdf_path'))
                ->when($filters['filter_status'] === 'pending', fn($q) => $q->whereNull('completed_pdf_path'))
                ->latest('created_at');

            $inspections = $inspectionQuery->get()->map(function (Inspection $ins) {
                $hasPdf = !empty($ins->completed_pdf_path) && Storage::disk('public')->exists($ins->completed_pdf_path);

                return [
                    'id'             => $ins->id,
                    'doc_no'         => $ins->report_id ?: "IR-{$ins->id}",
                    'raw_id'         => $ins->id,
                    'doc_type'       => 'inspection',
                    'doc_type_label' => 'Laporan Inspeksi',
                    'sub_type'       => $ins->device_category ? ucfirst($ins->device_category) : 'Pemeriksaan',
                    'user_name'      => $ins->user ?: '-',
                    'user_dept'      => $ins->department ?: '-',
                    'user_company'   => $ins->company ?: '-',
                    'device_name'    => $ins->device_name ?: '-',
                    'status'         => $hasPdf || $ins->completed_at ? 'completed' : 'draft',
                    'status_label'   => $hasPdf || $ins->completed_at ? 'Selesai' : 'Draft',
                    'has_pdf'        => $hasPdf,
                    'pdf_url'        => $hasPdf ? '/storage/' . ltrim($ins->completed_pdf_path, '/') : null,
                    'print_url'      => route('inspection.print', $ins->id),
                    'view_url'       => route('inspection.show', $ins->id),
                    'items_count'    => 1,
                    'remark'         => $ins->issue_description,
                    'created_at'     => $ins->created_at?->format('Y-m-d H:i:s') ?? '-',
                    'created_timestamp' => $ins->created_at?->timestamp ?? 0,
                ];
            });

            $all = $all->concat($inspections);
        }

        // Apply text search across all unified records
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $all = $all->filter(function ($doc) use ($search) {
                return str_contains(strtolower($doc['doc_no']), $search)
                    || str_contains(strtolower($doc['user_name']), $search)
                    || str_contains(strtolower($doc['user_dept']), $search)
                    || str_contains(strtolower($doc['user_company']), $search)
                    || str_contains(strtolower($doc['doc_type_label']), $search)
                    || str_contains(strtolower($doc['remark'] ?? ''), $search)
                    || str_contains(strtolower($doc['device_name'] ?? ''), $search);
            });
        }

        return $all->sortByDesc('created_timestamp')->values();
    }

    private function calculateStats(): array
    {
        $stbCount = Stb::where('document_type', '!=', 'loan')->count();
        $peminjamanCount = Stb::where('document_type', 'loan')->count();
        $inspectionCount = Inspection::count();

        $stbWithPdf = Stb::whereNotNull('completed_pdf_path')->count();
        $inspectionWithPdf = Inspection::whereNotNull('completed_pdf_path')->count();

        return [
            'total'      => $stbCount + $peminjamanCount + $inspectionCount,
            'stb'        => $stbCount,
            'peminjaman' => $peminjamanCount,
            'inspection' => $inspectionCount,
            'completed'  => $stbWithPdf + $inspectionWithPdf,
        ];
    }

    private function resolveFilters(Request $request): array
    {
        $search = trim((string) $request->string('search'));
        $filterType = trim((string) $request->string('filter_type'));
        $filterStatus = trim((string) $request->string('filter_status'));
        $fromDate = $this->normalizeDateFilter($request->string('from_date')->value());
        $toDate = $this->normalizeDateFilter($request->string('to_date')->value());

        if ($fromDate && $toDate && $fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'search'        => $search,
            'filter_type'   => $filterType,
            'filter_status' => $filterStatus,
            'from_date'     => $fromDate,
            'to_date'       => $toDate,
        ];
    }

    private function normalizeDateFilter(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value)?->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveStbDocNo(Stb $stb): string
    {
        $location = trim((string) ($stb->location_name ?? ''));
        $locationCode = $location !== '' && $location !== '-'
            ? strtoupper(substr((string) explode(' ', $location)[0], 0, 3))
            : 'ZGI';
        $dateCode = $stb->created_at?->format('ym') ?? now()->format('ym');
        $sequence = sprintf('%04d', $stb->id);
        $prefix = $stb->document_type === 'loan' ? 'LOAN' : 'STB';

        return "{$prefix}-{$locationCode}-{$dateCode}-{$sequence}";
    }
}
