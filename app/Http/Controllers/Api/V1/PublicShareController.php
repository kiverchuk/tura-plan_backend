<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class PublicShareController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $share = DB::table('shares')->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        abort_if(! $share, 404);
        $scope = json_decode($share->scope, true, 512, JSON_THROW_ON_ERROR);
        $query = DB::table('shifts')->where('user_id', $share->user_id)->whereNull('deleted_at');
        if (! empty($scope['calendarIds'])) {
            $query->whereIn('data->calendarId', $scope['calendarIds']);
        }if (! empty($scope['from'])) {
            $query->where('data->startsAt', '>=', $scope['from']);
        }if (! empty($scope['to'])) {
            $query->where('data->startsAt', '<', $scope['to']);
        }
        $data = $query->orderBy('data->startsAt')->limit(500)->get()->map(function ($row) {
            $shift = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
            unset($shift['note'],$shift['hourlyRateMinor'],$shift['currency'],$shift['tags']);

            return ['id' => $row->id, 'revision' => (int) $row->revision, 'shift' => $shift];
        });

        return response()->json(['data' => ['read_only' => true, 'shifts' => $data]]);
    }
}
