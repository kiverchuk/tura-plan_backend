<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ShareController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('shares')->where('user_id', $request->user()->getKey())->orderByDesc('created_at')->paginate(50)->through(fn ($s) => ['id' => $s->id, 'scope' => json_decode($s->scope, true), 'expires_at' => $s->expires_at, 'revoked_at' => $s->revoked_at])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['scope' => 'required|array|min:1', 'scope.calendarIds' => 'sometimes|array|max:20', 'scope.calendarIds.*' => 'uuid', 'scope.from' => 'sometimes|date', 'scope.to' => 'sometimes|date|after:scope.from', 'expires_at' => 'required|date|after:now']);
        if (now()->parse($data['expires_at'])->gt(now()->addDays(90))) {
            return response()->json(['error' => ['code' => 'expiry_too_long', 'message' => 'Share links may last no longer than 90 days.']], 422);
        }
        $scope = array_intersect_key($data['scope'], array_flip(['calendarIds', 'from', 'to']));
        if (isset($scope['calendarIds'])) {
            $owned = DB::table('calendars')->where('user_id', $request->user()->getKey())->whereIn('id', $scope['calendarIds'])->count();
            abort_unless($owned === count($scope['calendarIds']), 404);
        }
        $secret = Str::random(48);
        $id = (string) Str::uuid();
        DB::table('shares')->insert(['id' => $id, 'user_id' => $request->user()->getKey(), 'token_hash' => hash('sha256', $secret), 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'expires_at' => $data['expires_at'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'url' => url('/api/v1/shared/'.$secret), 'scope' => $scope, 'expires_at' => $data['expires_at'], 'read_only' => true]], 201);
    }

    public function show(Request $request, string $share): JsonResponse
    {
        $row = DB::table('shares')->where('user_id', $request->user()->getKey())->where('id', $share)->first();
        abort_if(! $row, 404);

        return response()->json(['data' => ['id' => $row->id, 'scope' => json_decode($row->scope, true), 'expires_at' => $row->expires_at, 'revoked_at' => $row->revoked_at]]);
    }

    public function update(Request $request, string $share): JsonResponse
    {
        $data = $request->validate(['scope' => 'sometimes|array', 'expires_at' => 'sometimes|date|after:now', 'revoke' => 'sometimes|boolean']);
        $row = DB::table('shares')->where('user_id', $request->user()->getKey())->where('id', $share)->first();
        abort_if(! $row, 404);
        $update = ['updated_at' => now()];
        if (isset($data['scope'])) {
            $update['scope'] = json_encode(array_intersect_key($data['scope'], array_flip(['calendarIds', 'from', 'to'])), JSON_THROW_ON_ERROR);
        }if (isset($data['expires_at'])) {
            $update['expires_at'] = $data['expires_at'];
        }if (($data['revoke'] ?? false) === true) {
            $update['revoked_at'] = now();
        }DB::table('shares')->where('id', $share)->update($update);

        return $this->show($request, $share);
    }

    public function destroy(Request $request, string $share): JsonResponse
    {
        $count = DB::table('shares')->where('user_id', $request->user()->getKey())->where('id', $share)->update(['revoked_at' => now(), 'updated_at' => now()]);
        abort_if(! $count,404);

        return response()->json(null,204);
    }
}
