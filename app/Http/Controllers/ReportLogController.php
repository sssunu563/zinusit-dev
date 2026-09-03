<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportLogController extends Controller
{
    /**
     * Item types for Report logs.
     */
    protected array $reportTypes = [
        'ServerOperation',
        'CctvOperation',
        'Bandwidth',
        'NetworkUptime',
        'AllReports',
        'InfraReport',
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
                'report_type' => $this->resolveReportTypeKey($log->item_type),
                'report_name' => $this->resolveReportName($log),
                'report_url' => $this->resolveReportUrl($log),
                'target_name' => $log->target ? $log->target->name : null,
                'date_meta' => $log->log_meta['date'] ?? null,
                'ok_count' => $log->log_meta['total_ok'] ?? ($log->log_meta['result']['ok'] ?? null),
                'fail_count' => $log->log_meta['total_fail'] ?? ($log->log_meta['result']['fail'] ?? null),
            ]);

        // Dynamic admins for reports
        $activeUserIds = ActionLog::whereIn('item_type', $this->reportTypes)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
        $admins = User::whereIn('id', $activeUserIds)->pluck('name')->toArray();
        $admins[] = 'System';

        $actions = ActionLog::whereIn('item_type', $this->reportTypes)
            ->distinct()
            ->pluck('action_type')
            ->values();

        $reportCategoryOptions = [
            ['key' => 'server', 'label' => 'Server Operation'],
            ['key' => 'cctv', 'label' => 'CCTV Operation'],
            ['key' => 'bandwidth', 'label' => 'Bandwidth Daily'],
            ['key' => 'uptime', 'label' => 'Network Uptime'],
            ['key' => 'all', 'label' => 'All Reports Sync'],
        ];

        // Stats summary
        $stats = [
            'total' => ActionLog::whereIn('item_type', $this->reportTypes)->count(),
            'server' => ActionLog::where('item_type', 'ServerOperation')->count(),
            'cctv' => ActionLog::where('item_type', 'CctvOperation')->count(),
            'bandwidth' => ActionLog::where('item_type', 'Bandwidth')->count(),
            'uptime' => ActionLog::where('item_type', 'NetworkUptime')->count(),
            'all_reports' => ActionLog::where('item_type', 'AllReports')->count(),
        ];

        return Inertia::render('ReportLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'filter_report', 'filter_admin', 'filter_action', 'from_date', 'to_date']),
            'filter_options' => [
                'admins' => array_values(array_unique($admins)),
                'actions' => $actions,
                'reports' => $reportCategoryOptions,
            ],
            'stats' => $stats,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->buildBaseQuery($request);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'Otorisator / Trigger', 'Jenis Report', 'Aksi', 'Catatan', 'Detail Hasil']);

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
                        $log->user ? $log->user->name : 'System (Scheduler)',
                        $this->resolveReportName($log),
                        strtoupper($log->action_type),
                        $log->note ?? '-',
                        $metaStr,
                    ]);
                }
            });

            fclose($handle);
        }, 'report_logs_' . date('Y_m_d_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildBaseQuery(Request $request): Builder
    {
        $query = ActionLog::with(['user', 'target'])
            ->whereIn('item_type', $this->reportTypes)
            ->latest();

        // 0. Global Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('action_type', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhere('log_meta', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // 1. Filter by Report Type
        if ($report = $request->input('filter_report')) {
            $matched = match (strtolower($report)) {
                'server' => ['ServerOperation'],
                'cctv' => ['CctvOperation'],
                'bandwidth' => ['Bandwidth'],
                'uptime' => ['NetworkUptime'],
                'all', 'allreports' => ['AllReports', 'InfraReport'],
                default => [$report],
            };

            $query->whereIn('item_type', $matched);
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

    private function resolveReportTypeKey(string $itemType): string
    {
        return match ($itemType) {
            'ServerOperation' => 'server',
            'CctvOperation' => 'cctv',
            'Bandwidth' => 'bandwidth',
            'NetworkUptime' => 'uptime',
            'AllReports' => 'all',
            default => 'report',
        };
    }

    private function resolveReportName($log): string
    {
        $type = (string)$log->item_type;
        return match ($type) {
            'ServerOperation' => 'Server Operation',
            'CctvOperation' => 'CCTV Operation',
            'Bandwidth' => 'Bandwidth Daily',
            'NetworkUptime' => 'Network Uptime',
            'AllReports' => 'All Reports Sync',
            'InfraReport' => 'Infra Report',
            default => $type,
        };
    }

    private function resolveReportUrl($log): ?string
    {
        $type = (string)$log->item_type;
        return match ($type) {
            'ServerOperation' => '/server-operation',
            'CctvOperation' => '/cctv-operation',
            'Bandwidth' => '/network-operation?tab=bandwidth',
            'NetworkUptime' => '/network-operation?tab=uptime',
            'AllReports', 'InfraReport' => '/infra-report',
            default => '/reports',
        };
    }

    private function resolveActionLabel(string $action): string
    {
        return match (strtolower($action)) {
            'auto_fetch' => 'Auto Fetch',
            'fetch' => 'Fetch Manual',
            'fetch_server_operation' => 'Fetch Server',
            'created', 'create' => 'Dibuat',
            'updated', 'update' => 'Diperbarui',
            'deleted', 'delete' => 'Dihapus',
            'maintenance' => 'Maintenance',
            'sync_failed' => 'Gagal Sinkronisasi',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}
