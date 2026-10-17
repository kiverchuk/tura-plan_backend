<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePremiumEntitlement
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasPremium = DB::table('entitlements')
            ->where('user_id', $request->user()->getKey())
            ->where('key', 'premium')
            ->whereIn('state', ['active', 'grace'])
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();

        if (! $hasPremium) {
            return response()->json(['error' => ['code' => 'premium_required', 'message' => 'An active TuraPlan Premium entitlement is required for cloud features.']], 403);
        }

        return $next($request);
    }
}
