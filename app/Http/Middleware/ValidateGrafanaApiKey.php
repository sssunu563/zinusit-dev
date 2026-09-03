<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateGrafanaApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = trim((string) config('services.grafana.api_key'));
        $providedKey = trim((string) ($request->header('X-Api-Key') ?: $request->query('api_key', '')));

        if ($configuredKey === '') {
            abort(503, 'Grafana API key is not configured.');
        }

        if (! hash_equals($configuredKey, $providedKey)) {
            abort(403, 'Invalid API key.');
        }

        return $next($request);
    }
}