<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SearchController extends Controller
{
    private const TABLES = ['shifts' => 'shifts', 'employers' => 'employers', 'personal-events' => 'personal_events', 'shift-types' => 'shift_types'];

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after:from'], 'employer' => ['nullable', 'uuid'], 'type' => ['nullable', 'string', 'max:40'], 'tag' => ['nullable', 'string', 'max:40'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $results = [];
        foreach (self::TABLES as $kind => $table) {
            $query = DB::table($table)->where('user_id', $request->user()->getKey())->whereNull('deleted_at');
            if (! empty($filters['q'])) {
                $query->where('data', 'like', '%'.addcslashes($filters['q'], '%_\\').'%');
            }
            if (! empty($filters['from'])) {
                $query->where('data->startsAt', '>=', $filters['from']);
            }
            if (! empty($filters['to'])) {
                $query->where('data->startsAt', '<', $filters['to']);
            }
            if ($table === 'shifts' && ! empty($filters['employer'])) {
                $query->where('data->employerId', $filters['employer']);
            }
            if ($table === 'shifts' && ! empty($filters['type'])) {
                $query->where('data->shiftTypeId', $filters['type']);
            }
            foreach ($query->limit(100)->get() as $row) {
                $results[] = ['id' => $row->id, 'type' => $kind, 'revision' => (int) $row->revision, 'data' => json_decode($row->data, true, 512, JSON_THROW_ON_ERROR)];
            }
        }

        return response()->json(['data' => array_slice($results, 0, 100), 'meta' => ['count' => min(count($results), 100), 'page' => 1]]);
    }
}
