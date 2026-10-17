<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RotationController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate(['anchor_date' => 'required|date_format:Y-m-d', 'days' => 'required|array|min:1|max:366', 'days.*.kind' => 'required|in:work,off', 'days.*.startLocal' => 'required_if:days.*.kind,work|nullable|date_format:H:i', 'days.*.endLocal' => 'required_if:days.*.kind,work|nullable|date_format:H:i', 'days.*.breakMinutes' => 'nullable|integer|min:0|max:1440', 'days.*.shiftTypeId' => 'nullable|uuid', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'timezone' => 'required|timezone']);
        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);
        abort_if($from->diffInDays($to) > 366, 422, 'Rotation preview range is limited to one year.');
        $proposed = [];
        $anchor = Carbon::parse($data['anchor_date']);
        $day = $from->copy();
        while ($day->lte($to)) {
            $offset = (($anchor->diffInDays($day, false) % count($data['days'])) + count($data['days'])) % count($data['days']);
            $rule = $data['days'][$offset];
            if ($rule['kind'] === 'work') {
                $start = Carbon::parse($day->format('Y-m-d').' '.($rule['startLocal'] ?? '09:00'), $data['timezone']);
                $end = Carbon::parse($day->format('Y-m-d').' '.($rule['endLocal'] ?? '17:00'), $data['timezone']);
                if ($end->lte($start)) {
                    $end->addDay();
                }$proposed[] = ['date' => $day->format('Y-m-d'), 'startsAt' => $start->utc()->toISOString(), 'endsAt' => $end->utc()->toISOString(), 'startsLocal' => $start->format('Y-m-d\TH:i:s'), 'endsLocal' => $end->format('Y-m-d\TH:i:s'), 'timezone' => $data['timezone'], 'breakMinutes' => $rule['breakMinutes'] ?? 0, 'shiftTypeId' => $rule['shiftTypeId'] ?? null];
            }$day->addDay();
        }
        $stored = DB::table('shifts')->where('user_id', $request->user()->getKey())->whereNull('deleted_at')->get();
        $conflicts = [];
        foreach ($proposed as $shift) {
            foreach ($stored as $existing) {
                $current = json_decode($existing->data, true);
                if (($shift['startsAt'] < $current['endsAt']) && ($current['startsAt'] < $shift['endsAt'])) {
                    $conflicts[] = ['proposed_date' => $shift['date'], 'existing_id' => $existing->id, 'existing_revision' => (int) $existing->revision];
                }
            }
        }

        return response()->json(['data' => ['proposed' => $proposed, 'conflicts' => $conflicts, 'count' => count($proposed), 'requires_conflict_strategy' => count($conflicts) > 0]]);
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate(['shifts' => 'required|array|min:1|max:400', 'shifts.*.date' => 'required|date_format:Y-m-d', 'shifts.*.startsAt' => 'required|date', 'shifts.*.endsAt' => 'required|date|after:shifts.*.startsAt', 'shifts.*.startsLocal' => 'required|string', 'shifts.*.endsLocal' => 'required|string', 'shifts.*.timezone' => 'required|timezone', 'shifts.*.breakMinutes' => 'nullable|integer|min:0|max:1440', 'conflict_strategy' => 'required|in:keep_existing,replace,allow', 'request_id' => 'required|uuid']);
        $userId = $request->user()->getKey();
        $ids = DB::transaction(function () use ($data, $userId) {
            $ids = [];
            foreach ($data['shifts'] as $shift) {
                $conflict = DB::table('shifts')->where('user_id', $userId)->whereNull('deleted_at')->where('data->startsAt', '<', $shift['endsAt'])->where('data->endsAt', '>', $shift['startsAt'])->exists();
                if ($conflict && $data['conflict_strategy'] === 'keep_existing') {
                    continue;
                }if ($conflict && $data['conflict_strategy'] === 'replace') {
                    DB::table('shifts')->where('user_id', $userId)->whereNull('deleted_at')->where('data->startsAt', '<', $shift['endsAt'])->where('data->endsAt', '>', $shift['startsAt'])->update(['deleted_at' => now(), 'updated_at' => now(), 'revision' => DB::raw('revision + 1')]);
                }$id = (string) Str::uuid();
                $payload = $shift + ['title' => 'Shift', 'currency' => 'RON', 'hourlyRateMinor' => 0];
                DB::table('shifts')->insert(['id' => $id, 'user_id' => $userId, 'data' => json_encode($payload, JSON_THROW_ON_ERROR), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('sync_mutations')->insert(['id' => (string) Str::uuid(), 'user_id' => $userId, 'entity_type' => 'shift', 'entity_id' => $id, 'operation' => 'upsert', 'payload' => json_encode($payload), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
                $ids[] = $id;
            }

return $ids;
        });

        return response()->json(['data' => ['created_ids' => $ids, 'count' => count($ids), 'undo_token' => $data['request_id']]]);
    }

    public function undo(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:400', 'ids.*' => 'uuid', 'revisions' => 'required|array']);
        $count = DB::table('shifts')->where('user_id', $request->user()->getKey())->whereIn('id', $data['ids'])->whereNull('deleted_at')->update(['deleted_at' => now(), 'updated_at' => now(), 'revision' => DB::raw('revision + 1')]);

        return response()->json(['data' => ['undone' => $count]]);
    }
}
