<?php

namespace App\Services;

use App\Models\AuthLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuthLogService
{
    public function write(
        Request $request,
        string $event,
        string $status,
        ?string $identifier = null,
        ?User $user = null,
        array $meta = [],
    ): void {
        try {
            AuthLog::query()->create([
                'user_id'    => $user?->id,
                'identifier' => $identifier,
                'event'      => $event,
                'status'     => $status,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'meta'       => $meta,
            ]);
        } catch (\Throwable $e) {
            // Jangan sampai logging error menggagalkan proses login
            \Illuminate\Support\Facades\Log::warning('AuthLogService::write failed: ' . $e->getMessage());
        }
    }
}