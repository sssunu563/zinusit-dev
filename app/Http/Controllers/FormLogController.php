<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\AuditSession;
use App\Models\Inspection;
use App\Models\Peminjaman;
use App\Models\Stb;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormLogController extends Controller
{
    /**
     * List of model classes that represent forms.
     */
    protected array $formTypes = [
        Stb::class,
        Peminjaman::class,
        Inspection::class,
        Ticket::class,
        AuditSession::class,
        'App\\Models\\Stb',
        'App\\Models\\Peminjaman',
        'App\\Models\\Inspection',
        'App\\Models\\Ticket',
        'App\\Models\\AuditSession',
        'Stb',
        'Peminjaman',
        'Inspection',
        'Ticket',
        'AuditSession',
    ];

    public function index(Request $request): Response
    {
        $query = $this->buildBaseQuery($request);

        $logs = $query->paginate(30)
            ->withQueryString()
            ->through(fn ($log) => [
                'id' => $log->id,
                'action_type' => $log->action_type,
                'action_label' => $this->resolveActionLabel($log->action_type),
                'note' => $log->note,
                'log_meta' => $log->log_meta,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'form_type' => $this->resolveFormTypeKey($log->item_type),
                'form_name' => $this->resolveFormName($log),
                'doc_no' => $this->resolveDocNumber($log),
                'doc_url' => $this->resolveDocUrl($log),
                'pdf_url' => $this->resolvePdfUrl($log),
                'role' => $log->log_meta['role'] ?? null,
                'target_name' => $this->resolveTargetName($log),
            ]);

        // Fetch dynamic filter options for form logs
        $activeUserIds = ActionLog::whereIn('item_type', $this->formTypes)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
        $admins = User::whereIn('id', $activeUserIds)->pluck('name')->toArray();
        $admins[] = 'System';

        $actions = ActionLog::whereIn('item_type', $this->formTypes)
            ->distinct()
            ->pluck('action_type')
            ->values();

        $formCategoryOptions = [
            ['key' => 'stb', 'label' => 'Dokumen STB'],
            ['key' => 'peminjaman', 'label' => 'Peminjaman'],
            ['key' => 'inspection', 'label' => 'Inspection'],
            ['key' => 'ticket', 'label' => 'Workspace / Tiket'],
            ['key' => 'audit', 'label' => 'Stock Opname'],
        ];

        // Stats summary
        $stats = [
            'total' => ActionLog::whereIn('item_type', $this->formTypes)->count(),
            'stb' => ActionLog::where(function ($q) {
                $q->where('item_type', Stb::class)->orWhere('item_type', 'like', '%Stb%');
            })->count(),
            'peminjaman' => ActionLog::where(function ($q) {
                $q->where('item_type', Peminjaman::class)->orWhere('item_type', 'like', '%Peminjaman%');
            })->count(),
            'inspection' => ActionLog::where(function ($q) {
                $q->where('item_type', Inspection::class)->orWhere('item_type', 'like', '%Inspection%');
            })->count(),
            'ticket' => ActionLog::where(function ($q) {
                $q->where('item_type', Ticket::class)->orWhere('item_type', 'like', '%Ticket%');
            })->count(),
        ];

        return Inertia::render('FormLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'filter_form', 'filter_admin', 'filter_action', 'from_date', 'to_date']),
            'filter_options' => [
                'admins' => array_values(array_unique($admins)),
                'actions' => $actions,
                'forms' => $formCategoryOptions,
            ],
            'stats' => $stats,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->buildBaseQuery($request);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'Otorisator / User', 'Jenis Form', 'No. Dokumen', 'Operasi', 'Role TTD', 'Catatan', 'Metadata']);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    $metaStr = '';
                    if (!empty($log->log_meta)) {
                        $metaStr = collect($log->log_meta)
                            ->filter(fn ($v) => $v !== null && $v !== '')
                            ->map(fn ($v, $k) => $k . ': ' . (is_scalar($v) ? (string)$v : json_encode($v)))
                            ->implode(' | ');
                    }

                    fputcsv($handle, [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user ? $log->user->name : 'System',
                        $this->resolveFormName($log),
                        $this->resolveDocNumber($log) ?? '-',
                        strtoupper($log->action_type),
                        $log->log_meta['role'] ?? '-',
                        $log->note ?? '-',
                        $metaStr,
                    ]);
                }
            });

            fclose($handle);
        }, 'form_logs_' . date('Y_m_d_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildBaseQuery(Request $request): Builder
    {
        $query = ActionLog::with(['user', 'item', 'target'])
            ->whereIn('item_type', $this->formTypes)
            ->latest();

        // 0. Global Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('action_type', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhere('item_id', 'like', "%{$search}%")
                  ->orWhere('log_meta', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // 1. Filter by Form Type
        if ($form = $request->input('filter_form')) {
            $matchedClasses = match (strtolower($form)) {
                'stb' => [Stb::class, 'App\\Models\\Stb', 'Stb'],
                'peminjaman' => [Peminjaman::class, 'App\\Models\\Peminjaman', 'Peminjaman'],
                'inspection' => [Inspection::class, 'App\\Models\\Inspection', 'Inspection'],
                'ticket', 'helpdesk' => [Ticket::class, 'App\\Models\\Ticket', 'Ticket'],
                'audit' => [AuditSession::class, 'App\\Models\\AuditSession', 'AuditSession'],
                default => null,
            };

            if ($matchedClasses) {
                $query->whereIn('item_type', $matchedClasses);
            } else {
                $query->where('item_type', 'like', "%{$form}%");
            }
        }

        // 2. Filter by Admin (Created By)
        if ($admin = $request->input('filter_admin')) {
            $query->where(function (Builder $q) use ($admin) {
                $q->whereHas('user', function ($uq) use ($admin) {
                    $uq->where('name', 'like', "%{$admin}%")
                       ->orWhere('username', 'like', "%{$admin}%");
                });

                if (stripos('System', $admin) !== false) {
                    $q->orWhereNull('user_id');
                }
            });
        }

        // 3. Filter by Action
        if ($action = $request->input('filter_action')) {
            $query->where('action_type', $action);
        }

        // 4. Date Filters
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query;
    }

    private function resolveFormTypeKey(string $itemType): string
    {
        $base = class_basename($itemType);
        return match ($base) {
            'Stb' => 'stb',
            'Peminjaman' => 'peminjaman',
            'Inspection' => 'inspection',
            'Ticket' => 'ticket',
            'AuditSession', 'AuditItem' => 'audit',
            default => 'form',
        };
    }

    private function resolveFormName($log): string
    {
        $type = $this->resolveFormTypeKey((string)$log->item_type);
        return match ($type) {
            'stb' => 'Dokumen STB',
            'peminjaman' => 'Peminjaman',
            'inspection' => 'Inspection',
            'ticket' => 'Workspace Ticket',
            'audit' => 'Stock Opname',
            default => class_basename($log->item_type),
        };
    }

    private function resolveDocNumber($log): ?string
    {
        $meta = $log->log_meta ?? [];

        if (!empty($meta['doc_no'])) {
            return $meta['doc_no'];
        }

        if (!empty($meta['report_id'])) {
            return $meta['report_id'];
        }

        if ($log->item) {
            $item = $log->item;
            if ($item instanceof Inspection && !empty($item->report_id)) {
                return $item->report_id;
            }
            if ($item instanceof Stb && !empty($item->batch_no)) {
                return $item->batch_no;
            }
            if ($item instanceof Ticket) {
                return "Tiket #{$item->id}";
            }
        }

        return $log->item_id ? "#{$log->item_id}" : null;
    }

    private function resolveDocUrl($log): ?string
    {
        if (!$log->item_id) {
            return null;
        }

        $type = $this->resolveFormTypeKey((string)$log->item_type);
        return match ($type) {
            'stb' => "/stb/{$log->item_id}/show",
            'peminjaman' => "/peminjaman/{$log->item_id}",
            'inspection' => "/inspection/{$log->item_id}",
            'ticket' => "/helpdesk/{$log->item_id}",
            'audit' => "/audit/{$log->item_id}",
            default => null,
        };
    }

    private function resolvePdfUrl($log): ?string
    {
        $meta = $log->log_meta ?? [];

        if (!empty($meta['pdf_path'])) {
            return '/storage/' . ltrim($meta['pdf_path'], '/');
        }

        if ($log->item) {
            $item = $log->item;
            if (!empty($item->completed_pdf_path)) {
                return '/storage/' . ltrim($item->completed_pdf_path, '/');
            }
        }

        return null;
    }

    private function resolveTargetName($log): ?string
    {
        if ($log->target && !empty($log->target->name)) {
            return $log->target->name;
        }

        $meta = $log->log_meta ?? [];
        if (!empty($meta['user'])) {
            return is_string($meta['user']) ? $meta['user'] : null;
        }

        return null;
    }

    private function resolveActionLabel(string $action): string
    {
        return match (strtolower($action)) {
            'created', 'create' => 'Dibuat',
            'updated', 'update' => 'Diperbarui',
            'sign' => 'Tanda Tangan',
            'sign_cleared' => 'Hapus TTD',
            'completed', 'stb_complete' => 'Diselesaikan',
            'cancelled' => 'Dibatalkan',
            'print' => 'Cetak',
            'sync_failed' => 'Gagal Sinkronisasi',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}
