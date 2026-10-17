<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResourceController extends Controller
{
    private const TABLES = ['employers' => 'employers', 'calendars' => 'calendars', 'shift-types' => 'shift_types', 'shifts' => 'shifts', 'personal-events' => 'personal_events', 'rotation-templates' => 'rotation_templates'];

    private const FIELDS = [
        'employers' => ['name', 'currency', 'color', 'archived', 'notes'],
        'calendars' => ['name', 'color', 'visible', 'timezone', 'region'],
        'shift-types' => ['name', 'color', 'icon', 'defaultStart', 'defaultEnd', 'breakMinutes', 'hourlyRateMinor', 'currency', 'employerId', 'archived'],
        'shifts' => ['title', 'shiftTypeId', 'employerId', 'calendarId', 'startsAt', 'endsAt', 'startsLocal', 'endsLocal', 'timezone', 'breakMinutes', 'hourlyRateMinor', 'currency', 'note', 'tags', 'planned', 'actual'],
        'personal-events' => ['title', 'startsAt', 'endsAt', 'startsLocal', 'endsLocal', 'timezone', 'allDay', 'note', 'calendarId', 'reminderMinutes'],
        'rotation-templates' => ['name', 'anchorDate', 'timezone', 'days', 'calendarId', 'employerId'],
    ];

    public function dispatch(Request $request, string $resource, ?string $id = null): JsonResponse
    {
        abort_unless(isset(self::TABLES[$resource]), 404);
        $table = self::TABLES[$resource];
        $userId = $request->user()->getKey();
        $query = DB::table($table)->where('user_id', $userId);
        if ($request->isMethod('get') && ! $id) {
            if (! $request->boolean('include_deleted')) {
                $query->whereNull('deleted_at');
            }
            $query->when($request->query('from'), fn (Builder $q, $from) => $q->where('data->startsAt', '>=', $from));
            $query->when($request->query('to'), fn (Builder $q, $to) => $q->where('data->startsAt', '<', $to));
            $rows = $query->orderBy('updated_at')->paginate(min(max((int) $request->query('per_page', 50), 1), 100));

            return response()->json(['data' => $rows->getCollection()->map(fn ($row) => $this->record($row)), 'meta' => ['current_page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()]]);
        }
        if ($request->isMethod('get')) {
            $row = $query->where('id', $id)->whereNull('deleted_at')->first();
            abort_if(! $row, 404);

            return response()->json(['data' => $this->record($row)]);
        }
        if ($request->isMethod('post')) {
            $data = $request->validate(['data' => ['required', 'array'], 'id' => ['sometimes', 'uuid']]);
            $clean = $this->clean($resource, $data['data']);
            $now = now();
            $uuid = $data['id'] ?? (string) Str::uuid();
            DB::table($table)->insert(['id' => $uuid, 'user_id' => $userId, 'data' => json_encode($clean, JSON_THROW_ON_ERROR), 'revision' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->recordMutation($request, $resource, $uuid, 'upsert', $clean, 1);

            return response()->json(['data' => ['id' => $uuid, 'revision' => 1, 'data' => $clean]], 201);
        }
        $row = $query->where('id', $id)->whereNull('deleted_at')->first();
        abort_if(! $row, 404);
        if ($request->isMethod('delete')) {
            $now = now();
            $revision = $row->revision + 1;
            DB::table($table)->where('user_id', $userId)->where('id', $id)->update(['deleted_at' => $now, 'updated_at' => $now, 'revision' => $revision]);
            $this->recordMutation($request, $resource, $id, 'delete', ['id' => $id], $revision, $now);

            return response()->json(null, 204);
        }
        $data = $request->validate(['data' => ['required', 'array'], 'revision' => ['required', 'integer', 'min:1']]);
        if ((int) $data['revision'] !== (int) $row->revision) {
            return response()->json(['error' => ['code' => 'revision_conflict', 'message' => 'The record changed since it was read.', 'current_revision' => (int) $row->revision, 'current' => $this->record($row)]], 409);
        }
        $clean = array_replace(json_decode($row->data, true, 512, JSON_THROW_ON_ERROR), $this->clean($resource, $data['data']));
        $revision = $row->revision + 1;
        $now = now();
        DB::table($table)->where('user_id', $userId)->where('id', $id)->where('revision', $row->revision)->update(['data' => json_encode($clean, JSON_THROW_ON_ERROR), 'revision' => $revision, 'updated_at' => $now]);
        $this->recordMutation($request, $resource, $id, 'upsert', $clean, $revision);

        return response()->json(['data' => ['id' => $id, 'revision' => $revision, 'data' => $clean]]);
    }

    private function clean(string $resource, array $data): array
    {
        $allowed = self::FIELDS[$resource];
        $clean = array_intersect_key($data, array_flip($allowed));
        if (count($clean) !== count($data)) {
            abort(422, 'Unsupported resource fields.');
        }
        if (isset($clean['currency']) && ! preg_match('/^[A-Z]{3}$/', $clean['currency'])) {
            abort(422, 'Currency must be an ISO 4217 code.');
        }
        foreach (['startsAt', 'endsAt'] as $key) {
            if (isset($clean[$key]) && ! strtotime((string) $clean[$key])) {
                abort(422, "Invalid {$key} date.");
            }
        }
        if ($resource === 'shifts' && isset($clean['startsAt'], $clean['endsAt']) && strtotime($clean['endsAt']) <= strtotime($clean['startsAt'])) {
            abort(422, 'Shift end must follow shift start.');
        }
        if (isset($clean['note'])) {
            $clean['note'] = mb_substr((string) $clean['note'], 0, 2000);
        }

        return $clean;
    }

    private function record(object $row): array
    {
        return ['id' => $row->id, 'revision' => (int) $row->revision, 'data' => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR), 'created_at' => $row->created_at, 'updated_at' => $row->updated_at, 'deleted_at' => $row->deleted_at];
    }

    private function recordMutation(Request $request, string $type, string $id, string $operation, array $payload, int $revision, mixed $deletedAt = null): void
    {
        DB::table('sync_mutations')->insert(['id' => (string) Str::uuid(), 'user_id' => $request->user()->getKey(), 'entity_type' => $type, 'entity_id' => $id, 'operation' => $operation, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'revision' => $revision, 'deleted_at' => $deletedAt, 'created_at' => now(), 'updated_at' => now()]);
    }
}
