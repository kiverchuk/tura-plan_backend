<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SyncController extends Controller
{
    private const TABLES = ['employer' => 'employers', 'calendar' => 'calendars', 'shift-type' => 'shift_types', 'shift' => 'shifts', 'personal-event' => 'personal_events', 'rotation-template' => 'rotation_templates'];

    private const FIELDS = ['employer' => ['name', 'currency', 'color', 'archived', 'notes'], 'calendar' => ['name', 'color', 'visible', 'timezone', 'region'], 'shift-type' => ['name', 'color', 'icon', 'defaultStart', 'defaultEnd', 'breakMinutes', 'hourlyRateMinor', 'currency', 'employerId', 'archived'], 'shift' => ['title', 'shiftTypeId', 'employerId', 'calendarId', 'rotationId', 'startsAt', 'endsAt', 'startsLocal', 'endsLocal', 'timezone', 'breakMinutes', 'hourlyRateMinor', 'currency', 'note', 'tags', 'planned', 'actual'], 'personal-event' => ['title', 'startsAt', 'endsAt', 'startsLocal', 'endsLocal', 'timezone', 'allDay', 'note', 'calendarId', 'reminderMinutes'], 'rotation-template' => ['name', 'anchorDate', 'timezone', 'days', 'calendarId', 'employerId']];

    public function push(Request $request): JsonResponse
    {
        $data = $request->validate(['mutations' => 'required|array|min:1|max:500', 'mutations.*.id' => 'required|uuid', 'mutations.*.entityType' => 'required|string', 'mutations.*.entityId' => 'required|uuid', 'mutations.*.operation' => 'required|in:upsert,delete', 'mutations.*.payload' => 'required|array', 'mutations.*.baseRevision' => 'nullable|integer|min:1']);
        $userId = $request->user()->getKey();
        $applied = [];
        $conflicts = [];
        foreach ($data['mutations'] as $mutation) {
            $result = DB::transaction(function () use ($mutation, $userId): array {
                $prior = DB::table('sync_mutations')->where('user_id', $userId)->where('id', $mutation['id'])->first();
                if ($prior) {
                    return ['id' => $mutation['id'], 'status' => 'duplicate', 'revision' => (int) $prior->revision];
                }
                $priorConflict = DB::table('sync_conflicts')->where('user_id', $userId)->where('id', $mutation['id'])->first();
                if ($priorConflict && $priorConflict->status === 'resolved') {
                    return ['id' => $mutation['id'], 'status' => 'duplicate', 'revision' => (int) $priorConflict->remote_revision];
                }
                $type = $mutation['entityType'];
                abort_unless(isset(self::TABLES[$type]), 422, 'Unsupported sync entity.');
                $table = self::TABLES[$type];
                $uuid = $mutation['entityId'];
                $record = DB::table($table)->where('user_id', $userId)->where('id', $uuid)->lockForUpdate()->first();
                if ($record && $mutation['operation'] === 'upsert' && (int) ($mutation['baseRevision'] ?? 0) !== (int) $record->revision) {
                    DB::table('sync_conflicts')->updateOrInsert(
                        ['user_id' => $userId, 'id' => $mutation['id']],
                        ['entity_type' => $type, 'entity_id' => $uuid, 'base_revision' => $mutation['baseRevision'] ?? null, 'remote_revision' => (int) $record->revision, 'local_payload' => json_encode($mutation['payload'], JSON_THROW_ON_ERROR), 'remote_payload' => $record->data, 'status' => 'pending', 'resolution' => null, 'resolved_at' => null, 'created_at' => now(), 'updated_at' => now()],
                    );

                    return ['id' => $mutation['id'], 'status' => 'conflict', 'current' => ['revision' => (int) $record->revision, 'data' => json_decode($record->data, true, 512, JSON_THROW_ON_ERROR)]];
                }
                $revision = $record ? (int) $record->revision + 1 : 1;
                $now = now();
                if ($mutation['operation'] === 'delete') {
                    if ($record) {
                        DB::table($table)->where('user_id', $userId)->where('id', $uuid)->update(['revision' => $revision, 'deleted_at' => $now, 'updated_at' => $now]);
                    } else {
                        $revision = (int) ($mutation['baseRevision'] ?? 1) + 1;
                    }
                    $payload = ['id' => $uuid];
                    $deletedAt = $now;
                } else {
                    $payload = array_intersect_key($mutation['payload'], array_flip(self::FIELDS[$type]));
                    if (count($payload) !== count($mutation['payload'])) {
                        abort(422, 'Unsupported sync fields.');
                    }
                    $values = ['data' => json_encode($payload, JSON_THROW_ON_ERROR), 'revision' => $revision, 'updated_at' => $now, 'deleted_at' => null];
                    if ($record) {
                        DB::table($table)->where('user_id', $userId)->where('id', $uuid)->update($values);
                    } else {
                        DB::table($table)->insert(['id' => $uuid, 'user_id' => $userId, ...$values, 'created_at' => $now]);
                    }
                    $deletedAt = null;
                }
                DB::table('sync_mutations')->insert(['id' => $mutation['id'], 'user_id' => $userId, 'entity_type' => $type, 'entity_id' => $uuid, 'operation' => $mutation['operation'], 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'revision' => $revision, 'deleted_at' => $deletedAt, 'created_at' => $now, 'updated_at' => $now]);

                return ['id' => $mutation['id'], 'status' => 'applied', 'revision' => $revision];
            });
            if ($result['status'] === 'conflict') {
                $conflicts[] = $result;
            } else {
                $applied[] = $result;
            }
        }

        return response()->json(['data' => ['applied' => $applied, 'conflicts' => $conflicts]]);
    }

    public function pull(Request $request): JsonResponse
    {
        $data = $request->validate(['cursor' => ['nullable', 'integer', 'min:0'], 'limit' => ['nullable', 'integer', 'min:1', 'max:500']]);
        $rows = DB::table('sync_mutations')->where('user_id', $request->user()->getKey())->where('sequence', '>', (int) ($data['cursor'] ?? 0))->orderBy('sequence')->limit((int) ($data['limit'] ?? 200))->get();
        $changes = $rows->map(fn ($row) => ['id' => $row->id, 'entityType' => $row->entity_type, 'entityId' => $row->entity_id, 'operation' => $row->operation, 'payload' => json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR), 'revision' => (int) $row->revision, 'deletedAt' => $row->deleted_at, 'sequence' => (int) $row->sequence]);

        return response()->json(['data' => ['changes' => $changes, 'cursor' => $rows->last()?->sequence ?? (int) ($data['cursor'] ?? 0)]]);
    }

    public function resolve(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['strategy' => 'required|in:local,remote,merge', 'payload' => 'required_if:strategy,merge|array']);
        $userId = $request->user()->getKey();
        $conflict = DB::table('sync_conflicts')->where('user_id', $userId)->where('id', $id)->where('status', 'pending')->first();
        abort_if(! $conflict, 404);
        if ($data['strategy'] === 'remote') {
            DB::table('sync_conflicts')->where('id', $id)->update(['status' => 'resolved', 'resolution' => 'remote', 'resolved_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => ['resolved' => true, 'strategy' => 'remote', 'revision' => (int) $conflict->remote_revision]]);
        }
        $payload = $data['strategy'] === 'merge' ? $data['payload'] : json_decode($conflict->local_payload, true, 512, JSON_THROW_ON_ERROR);
        $request->merge(['mutations' => [['id' => (string) Str::uuid(), 'entityType' => $conflict->entity_type, 'entityId' => $conflict->entity_id, 'operation' => 'upsert', 'payload' => $payload, 'baseRevision' => (int) $conflict->remote_revision]]]);
        $response = $this->push($request);
        $result = $response->getData(true);
        if ($response->getStatusCode() < 400 && ! empty($result['data']['applied'])) {
            DB::table('sync_conflicts')->where('user_id', $userId)->where('id', $id)->update(['status' => 'resolved', 'resolution' => $data['strategy'], 'resolved_at' => now(), 'updated_at' => now()]);
        }

        return $response;
    }
}
