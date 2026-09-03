<?php

namespace App\Http\Controllers;

use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActionLogController extends Controller
{
    /**
     * Allowed item types for Activity Logs (only Asset and User).
     */
    protected array $allowedItemTypes = [
        User::class,
        'App\\Models\\User',
        'User',
        'snipeit_assets',
        'snipeit_hardware',
        'snipeit_laptop',
        'snipeit_license',
        'snipeit_accessories',
        'snipeit_consumable',
        'snipeit_component',
    ];

    public function index(Request $request): Response
    {
        $query = $this->buildBaseQuery($request);

        $logs = $query->paginate(30)
            ->withQueryString()
            ->through(fn ($log) => [
                'id' => $log->id,
                'action_type' => $this->normalizeActionType($log->action_type),
                'action_label' => $this->resolveActionLabel($log->action_type),
                'note' => $this->resolveNote($log),
                'log_meta' => $log->log_meta,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                ] : null,
                'item_type' => str_replace('snipeit_', '', class_basename((string)$log->item_type)),
                'item_raw_type' => (string)$log->item_type,
                'item_url' => $this->resolveItemUrl($log, 'item'),
                'item_id' => $log->item_id ?? $log->snipeit_id,
                'item_name' => $this->resolveItemName($log, 'item'),
                'target_type' => str_replace('snipeit_', '', class_basename((string)$log->target_type)),
                'target_url' => $this->resolveItemUrl($log, 'target'),
                'target_name' => $this->resolveItemName($log, 'target'),
                'category' => $this->resolveCategory($log),
            ]);

        // Fetch dynamic categories (only assets & users)
        $activeUserIds = $this->scopeAllowedLogs(ActionLog::query())
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');
        $admins = User::whereIn('id', $activeUserIds)->pluck('name')->toArray();
        $admins[] = 'System';
        
        $actions = $this->scopeAllowedLogs(ActionLog::query())
            ->distinct()
            ->pluck('action_type')
            ->map(fn ($act) => $this->normalizeActionType($act))
            ->unique()
            ->values();

        // Category filter options
        $categoryOptions = [
            ['key' => 'assets', 'label' => 'Aset & Perangkat'],
            ['key' => 'users', 'label' => 'Pengguna & Akun'],
        ];

        // Stats summary
        $stats = [
            'total' => $this->scopeAllowedLogs(ActionLog::query())->count(),
            'assets' => ActionLog::where(function ($q) {
                $q->where('item_type', 'like', 'snipeit_%')
                  ->orWhere('item_type', 'like', '%Asset%');
            })->count(),
            'users' => ActionLog::where(function ($q) {
                $q->where('item_type', User::class)
                  ->orWhere('item_type', 'like', '%User%');
            })->count(),
        ];

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'filter_category', 'filter_admin', 'filter_action', 'from_date', 'to_date']),
            'filter_options' => [
                'admins' => array_values(array_unique($admins)),
                'actions' => $actions,
                'categories' => $categoryOptions,
            ],
            'stats' => $stats,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->buildBaseQuery($request);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Waktu', 'Otorisator / User', 'Kategori', 'Entitas Item', 'Aksi', 'Target', 'Catatan']);

            $query->chunk(200, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user ? $log->user->name : 'System',
                        $this->resolveCategory($log),
                        $this->resolveItemName($log, 'item') ?? '-',
                        strtoupper($log->action_type),
                        $this->resolveItemName($log, 'target') ?? '-',
                        $this->resolveNote($log) ?? '-',
                    ]);
                }
            });

            fclose($handle);
        }, 'activity_logs_' . date('Y_m_d_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildBaseQuery(Request $request): Builder
    {
        $query = ActionLog::with(['user', 'item', 'target'])->latest();
        $query = $this->scopeAllowedLogs($query);

        // 0. Global Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('action_type', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%")
                  ->orWhere('item_id', 'like', "%{$search}%")
                  ->orWhere('snipeit_id', 'like', "%{$search}%")
                  ->orWhere('log_meta', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // 1. Filter by Category
        if ($category = $request->input('filter_category')) {
            if ($category === 'assets') {
                $query->where(function ($q) {
                    $q->where('item_type', 'like', 'snipeit_%')
                      ->orWhere('item_type', 'like', '%Asset%');
                });
            } elseif ($category === 'users') {
                $query->where(function ($q) {
                    $q->where('item_type', User::class)
                      ->orWhere('item_type', 'like', '%User%');
                });
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
            $normalized = $this->normalizeActionType($action);
            if ($normalized === 'created') {
                $query->whereIn('action_type', ['created', 'create']);
            } elseif ($normalized === 'updated') {
                $query->whereIn('action_type', ['updated', 'update']);
            } else {
                $query->where('action_type', $action);
            }
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

    /**
     * Scope query to strictly Asset & User items.
     */
    protected function scopeAllowedLogs(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('item_type', User::class)
              ->orWhere('item_type', 'like', '%User%')
              ->orWhere('item_type', 'like', 'snipeit_%')
              ->orWhere('item_type', 'like', '%Asset%');
        });
    }

    private function resolveCategory($log): string
    {
        $type = (string)$log->item_type;

        if (str_starts_with($type, 'snipeit_') || str_contains($type, 'Asset')) {
            return 'Aset & Perangkat';
        }
        if (str_contains($type, 'User')) {
            return 'Pengguna & Akun';
        }

        return 'Aset / Pengguna';
    }

    private function resolveItemName($log, string $relation = 'item'): ?string
    {
        $model = $relation === 'item' ? $log->item : $log->target;
        $type = $relation === 'item' ? $log->item_type : $log->target_type;
        $id = $relation === 'item' ? ($log->item_id ?? $log->snipeit_id) : $log->target_id;
        $meta = $log->log_meta ?? [];

        if ($model) {
            if (!empty($model->name)) return $model->name;
            if (!empty($model->username)) return $model->username;
            if (!empty($model->title)) return $model->title;
            if (!empty($model->device_name)) return $model->device_name;
            return class_basename($model) . " #{$model->getKey()}";
        }

        // Use name from meta if available (for Snipe-IT items)
        if ($relation === 'item') {
            if (!empty($meta['item_name'])) return $meta['item_name'];
            if (!empty($meta['name'])) return $meta['name'];
            if (!empty($meta['asset_tag'])) return $meta['asset_tag'];
        }
        if ($relation === 'target') {
            if (!empty($meta['target_name'])) return $meta['target_name'];
            if (!empty($meta['recipient'])) return $meta['recipient'];
        }

        // Handle Snipe-IT items which don't have local models
        if (str_starts_with((string)$type, 'snipeit_')) {
            $label = str_replace('snipeit_', '', (string)$type);
            $prefix = match(strtolower($label)) {
                'assets', 'hardware' => 'Asset',
                'laptop' => 'Laptop',
                'license' => 'License',
                'accessories' => 'Accessory',
                'consumable' => 'Consumable',
                'component' => 'Component',
                default => ucfirst($label)
            };
            return "{$prefix} #{$id}";
        }
        
        return $id ? "ID: {$id}" : null;
    }

    private function resolveItemUrl($log, string $relation = 'item'): ?string
    {
        $model = $relation === 'item' ? $log->item : $log->target;
        $type = $relation === 'item' ? $log->item_type : $log->target_type;
        $id = $relation === 'item' ? ($log->item_id ?? $log->snipeit_id) : $log->target_id;

        if ($model) {
            $class = class_basename($model);
            if ($class === 'User') return "/users/{$model->getKey()}";
            return null;
        }

        if (str_starts_with((string)$type, 'snipeit_')) {
            $label = str_replace('snipeit_', '', (string)$type);
            $mappedType = match (strtolower($label)) {
                'hardware', 'laptop' => 'assets',
                default => $label,
            };
            return "/asset/{$id}?type={$mappedType}";
        }
        
        return null;
    }

    private function resolveNote($log): ?string
    {
        if (!empty($log->note)) {
            return $log->note;
        }

        $meta = $log->log_meta ?? [];
        $itemName = $this->resolveItemName($log, 'item') ?? 'Aset';
        $action = strtolower((string)$log->action_type);

        if (in_array($action, ['create', 'created'])) {
            return "Menambahkan aset baru: {$itemName}";
        }
        if (in_array($action, ['update', 'updated'])) {
            return "Memperbarui rincian data aset: {$itemName}";
        }
        if ($action === 'add_stock') {
            $qty = $meta['added_qty'] ?? 1;
            $newQty = $meta['new_qty'] ?? null;
            return "Tambah stok +{$qty}" . ($newQty ? " (total: {$newQty})" : "");
        }
        if ($action === 'checkout') {
            $target = $meta['recipient'] ?? ($meta['target_name'] ?? 'pengguna');
            return "Check out aset ke {$target}";
        }
        if ($action === 'checkin') {
            return "Check in {$itemName} ke inventaris";
        }

        return null;
    }

    private function normalizeActionType(string $action): string
    {
        return match (strtolower($action)) {
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            default => strtolower($action),
        };
    }

    private function resolveActionLabel(string $action): string
    {
        return match (strtolower($action)) {
            'created', 'create' => 'Dibuat',
            'updated', 'update' => 'Diperbarui',
            'deleted', 'delete' => 'Dihapus',
            'login' => 'Login',
            'logout' => 'Logout',
            'add_stock' => 'Tambah Stok',
            'checkout' => 'Check Out',
            'checkin' => 'Check In',
            'sync' => 'Sinkronisasi',
            'upload' => 'Unggah File',
            default => ucfirst(str_replace('_', ' ', $action)),
        };
    }
}
