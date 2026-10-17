<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::select('select 1');

            return response()->json(['status' => 'ready', 'database' => 'ok']);
        } catch (\Throwable) {
            return response()->json(['status' => 'not_ready', 'database' => 'unavailable'], 503);
        }
    }

    public function config(): JsonResponse
    {
        return response()->json(['data' => ['api_version' => 'v1', 'supported_locales' => ['en', 'ro', 'ru', 'uk', 'de', 'fr', 'es'], 'default_region' => 'RO', 'supported_regions' => ['RO', 'MD'], 'analytics' => ['provider' => 'firebase', 'enabled' => false], 'cloud_sync' => ['enabled' => true], 'premium' => ['enabled' => false]]]);
    }

    public function flags(): JsonResponse
    {
        return response()->json(['data' => ['cloud_sync' => true, 'calendar_sharing' => true, 'store_billing' => false, 'holiday_packs' => true, 'firebase_analytics' => false]]);
    }
}
