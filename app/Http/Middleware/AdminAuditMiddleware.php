<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class AdminAuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();
        if ($user && $request->is('admin/*')) {
            DB::table('admin_audit_logs')->insert(['admin_user_id' => $user->getKey(), 'action' => strtoupper($request->method()) === 'GET' ? 'admin.read' : 'admin.write', 'subject_type' => substr((string) $request->route()?->getName(), 0, 80) ?: null, 'subject_id' => substr((string) $request->route('record') ?? '', 0, 80) ?: null, 'metadata' => json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        }

        return $response;
    }
}
