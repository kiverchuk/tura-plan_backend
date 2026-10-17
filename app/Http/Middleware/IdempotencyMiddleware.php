<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientKey = $request->header('Idempotency-Key');
        if (! is_string($clientKey) || strlen($clientKey) < 8 || strlen($clientKey) > 120) {
            return response()->json(['error' => ['code' => 'idempotency_key_required', 'message' => 'Provide an Idempotency-Key header.']], 428);
        }
        $key = hash('sha256', $request->method().'|'.$request->route()->uri().'|'.$clientKey);
        $userId = $request->user()->getKey();
        $cached = DB::table('idempotency_keys')->where('user_id', $userId)->where('key', $key)->where('expires_at', '>', now())->first();
        if ($cached && $cached->status_code) {
            return response()->json(json_decode($cached->response ?? 'null', true), $cached->status_code);
        }
        $response = $next($request);
        if ($response->getStatusCode() < 500) {
            $body = json_decode($response->getContent() ?: 'null', true);
            DB::table('idempotency_keys')->updateOrInsert(['user_id' => $userId, 'key' => $key], ['status_code' => $response->getStatusCode(), 'response' => json_encode($body, JSON_THROW_ON_ERROR), 'expires_at' => now()->addDays(2), 'created_at' => now(), 'updated_at' => now()]);
        }

        return $response;
    }
}
