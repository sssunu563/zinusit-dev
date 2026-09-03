<?php

namespace App\Http\Controllers;

use App\Models\AuthLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class AuthLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->resolveFilters($request);

        $baseQuery = AuthLog::query();
        $stats = [
            'total'   => (clone $baseQuery)->count(),
            'success' => (clone $baseQuery)->whereIn('status', ['success', 'matched'])->count(),
            'failed'  => (clone $baseQuery)->where('status', 'failed')->count(),
            'logout'  => (clone $baseQuery)->where('event', 'logout')->count(),
            'sync'    => (clone $baseQuery)->where('event', 'user_sync')->count(),
        ];

        $logs = $this->buildFilteredQuery($filters)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuthLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'event_label' => $this->resolveEventLabel($log->event),
                'status' => $log->status,
                'status_label' => $this->resolveStatusLabel($log->status),
                'identifier' => $log->identifier,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'meta' => $log->meta,
                'created_at' => optional($log->created_at)?->format('Y-m-d H:i:s') ?? optional($log->created_at)?->toIso8601String(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'username' => $log->user->username,
                    'avatar' => $log->user->avatar,
                    'department' => $log->user->department,
                    'company' => $log->user->company,
                ] : null,
            ]);

        return Inertia::render('AuthLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $stats,
            'events' => ['login', 'logout', 'user_sync'],
            'statuses' => ['success', 'failed', 'matched', 'updated', 'created'],
        ]);
    }

    private function resolveEventLabel(string $event): string
    {
        return match ($event) {
            'login' => 'Masuk (Login)',
            'logout' => 'Keluar (Logout)',
            'user_sync' => 'Sinkronisasi Akun',
            default => ucwords(str_replace('_', ' ', $event)),
        };
    }

    private function resolveStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => 'Berhasil',
            'failed' => 'Gagal',
            'matched' => 'Tervalidasi',
            'updated' => 'Diperbarui',
            'created' => 'Dibuat',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->resolveFilters($request);
        $filename = 'auth-logs-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Event',
                'Status',
                'User Name',
                'User Email',
                'Username',
                'Identifier',
                'IP Address',
                'User Agent',
                'Meta',
            ]);

            $this->buildFilteredQuery($filters)
                ->latest()
                ->chunk(500, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            optional($log->created_at)?->toDateTimeString(),
                            $log->event,
                            $log->status,
                            $log->user?->name,
                            $log->user?->email,
                            $log->user?->username,
                            $log->identifier,
                            $log->ip_address,
                            $log->user_agent,
                            $this->formatMetaForExport($log->meta),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilteredQuery(array $filters)
    {
        return AuthLog::query()
            ->with('user:id,name,email,username,avatar,department,company')
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($nested) use ($filters) {
                    $nested
                        ->where('identifier', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('ip_address', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('user', function ($userQuery) use ($filters) {
                            $userQuery
                                ->where('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('username', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when($filters['event'] !== '', fn ($query) => $query->where('event', $filters['event']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['from_date'], fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when($filters['to_date'], fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']));
    }

    private function resolveFilters(Request $request): array
    {
        $search = trim((string) $request->string('search'));
        $event = trim((string) $request->string('event'));
        $status = trim((string) $request->string('status'));
        $fromDate = $this->normalizeDateFilter($request->string('from_date')->value());
        $toDate = $this->normalizeDateFilter($request->string('to_date')->value());

        if ($fromDate && $toDate && $fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [
            'search' => $search,
            'event' => $event,
            'status' => $status,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    private function formatMetaForExport(?array $meta): string
    {
        if (!$meta) {
            return '';
        }

        return collect($meta)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, $key) => $key . ': ' . (is_scalar($value) ? (string) $value : json_encode($value)))
            ->implode(' | ');
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
}