<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('devices')->where('user_id', $request->user()->getKey())->whereNull('revoked_at')->get(['id', 'name', 'platform', 'app_version', 'last_seen_at', 'created_at'])]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $device = DB::table('devices')->where('user_id', $request->user()->getKey())->where('id', $id)->first();
        abort_if(! $device, 404);
        DB::table('devices')->where('id', $id)->update(['revoked_at' => now(), 'updated_at' => now()]);
        $request->user()->tokens()->where('name', $id)->delete();

        return response()->json(null, 204);
    }
}
