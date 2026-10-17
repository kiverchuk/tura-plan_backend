<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrivacyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PrivacyController extends Controller
{
    public function request(Request $request, string $action): JsonResponse
    {
        $user = $request->user();
        $data = match ($action) {
            'export' => $request->validate([]),'rectify' => $request->validate(['changes' => 'required|array:name,locale,region', 'changes.name' => ['sometimes', 'string', 'max:120'], 'changes.locale' => ['sometimes', 'in:en,ro,ru,uk,de,fr,es'], 'changes.region' => ['sometimes', 'string', 'max:16']]),'erase' => $request->validate(['password' => 'required|string']),default => abort(404)
        };
        if ($action === 'erase' && ! Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => ['code' => 'reauthentication_failed', 'message' => 'Confirm your password to request account deletion.']], 422);
        }
        $id = (string) Str::uuid();
        $details = $action === 'rectify' ? ['changes' => $data['changes']] : [];
        DB::table('privacy_requests')->insert(['id' => $id, 'user_id' => $user->getKey(), 'kind' => $action, 'status' => 'pending', 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        if ($action === 'erase') {
            $user->forceFill(['erasure_requested_at' => now()])->save();
            $user->tokens()->delete();
            DB::table('devices')->where('user_id', $user->getKey())->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('shares')->where('user_id', $user->getKey())->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
        }
        ProcessPrivacyRequest::dispatch($id);

        return response()->json(['data' => ['id' => $id, 'kind' => $action, 'status' => 'pending', 'created_at' => now()->toISOString(), 'store_subscription_notice' => $action === 'erase' ? 'Cancel subscriptions separately in Apple App Store or Google Play.' : null]], 202);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $row = DB::table('privacy_requests')->where('user_id', $request->user()->getKey())->where('id', $id)->first();
        abort_if(! $row, 404);
        $details = json_decode($row->details ?? '{}', true, 512, JSON_THROW_ON_ERROR);

        return response()->json(['data' => ['id' => $row->id, 'kind' => $row->kind, 'status' => $row->status, 'created_at' => $row->created_at, 'completed_at' => $row->completed_at, 'download_available' => isset($details['export_path'])]]);
    }

    public function publicStatus(string $id): JsonResponse
    {
        $row = DB::table('privacy_requests')->where('id', $id)->first();
        abort_if(! $row, 404);

        return response()->json(['data' => ['id' => $row->id, 'kind' => $row->kind, 'status' => $row->status, 'created_at' => $row->created_at, 'completed_at' => $row->completed_at]]);
    }

    public function download(Request $request, string $id)
    {
        $row = DB::table('privacy_requests')->where('user_id', $request->user()->getKey())->where('id', $id)->where('kind', 'export')->where('status', 'completed')->first();
        abort_if(! $row, 404);
        $details = json_decode($row->details ?? '{}', true, 512, JSON_THROW_ON_ERROR);
        $path = $details['export_path'] ?? null;
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path,'turaplan-data-export.json');
    }
}
