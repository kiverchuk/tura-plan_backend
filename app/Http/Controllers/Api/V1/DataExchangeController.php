<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DataExchangeController extends Controller
{
    private const TABLES = ['employers' => 'employers', 'calendars' => 'calendars', 'shift_types' => 'shift-types', 'shifts' => 'shifts', 'personal_events' => 'personal-events', 'rotation_templates' => 'rotation-templates', 'pay_rules' => 'pay-rules'];

    public function export(Request $request): JsonResponse
    {
        $format = $request->validate(['format' => ['required', 'in:json,ics,csv,pdf'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after:from']])['format'];
        $id = (string) Str::uuid();
        $userId = $request->user()->getKey();
        DB::table('export_requests')->insert(['id' => $id, 'user_id' => $userId, 'format' => $format, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
        try {
            $rows = [];
            foreach (self::TABLES as $table => $kind) {
                $rows[$table] = DB::table($table)->where('user_id', $userId)->whereNull('deleted_at')->get()->map(fn ($r) => ['id' => $r->id, 'revision' => (int) $r->revision, 'data' => json_decode($r->data, true, 512, JSON_THROW_ON_ERROR)])->all();
            }
            if (isset($request['from']) || isset($request['to'])) {
                $rows['shifts'] = array_values(array_filter($rows['shifts'], fn ($r) => ($request->input('from') === null || ($r['data']['startsAt'] ?? '') >= $request->input('from')) && ($request->input('to') === null || ($r['data']['startsAt'] ?? '') < $request->input('to'))));
            }
            $path = "exports/{$userId}/{$id}.{$format}";
            $content = $this->render($format, $rows, $request->user()->name);
            Storage::disk('local')->put($path, $content);
            $expires = now()->addDays(7);
            DB::table('export_requests')->where('id', $id)->update(['status' => 'completed', 'path' => $path, 'expires_at' => $expires, 'updated_at' => now()]);

            return response()->json(['data' => ['id' => $id, 'format' => $format, 'status' => 'completed', 'download_url' => url("/api/v1/exports/{$id}/download"), 'expires_at' => $expires->toISOString()]], 202);
        } catch (\Throwable $e) {
            DB::table('export_requests')->where('id', $id)->update(['status' => 'failed', 'error' => 'Export could not be generated.', 'updated_at' => now()]);
            throw $e;
        }
    }

    public function exportStatus(Request $request, string $id): JsonResponse
    {
        $row = DB::table('export_requests')->where('user_id', $request->user()->getKey())->where('id', $id)->first();
        abort_if(! $row, 404);

        return response()->json(['data' => ['id' => $row->id, 'format' => $row->format, 'status' => $row->status, 'download_url' => $row->status === 'completed' && $row->expires_at > now() ? url("/api/v1/exports/{$id}/download") : null, 'expires_at' => $row->expires_at]]);
    }

    public function download(Request $request, string $id)
    {
        $row = DB::table('export_requests')->where('user_id', $request->user()->getKey())->where('id', $id)->where('status', 'completed')->first();
        abort_if(! $row || ! $row->expires_at || now()->gt($row->expires_at) || ! Storage::disk('local')->exists($row->path), 404);

        return Storage::disk('local')->download($row->path, "turaplan-export.{$row->format}");
    }

    public function validateImport(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preview($request)]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate(['backup' => 'required|array', 'strategy' => 'required|in:merge,replace,skip']);
        $preview = $this->preview($request);
        abort_if($preview['errors'], 422, 'Import backup contains invalid entities.');
        $userId = $request->user()->getKey();
        DB::transaction(function () use ($data, $userId) {
            if ($data['strategy'] === 'replace') {
                foreach (self::TABLES as $table => $kind) {
                    DB::table($table)->where('user_id', $userId)->delete();
                }
            }foreach (self::TABLES as $table => $kind) {
                foreach ($data['backup'][$table] ?? [] as $row) {
                    $id = $row['id'] ?? (string) Str::uuid();
                    $exists = DB::table($table)->where('user_id', $userId)->where('id', $id)->exists();
                    if ($exists && $data['strategy'] === 'skip') {
                        continue;
                    }$values = ['data' => json_encode($row['data'], JSON_THROW_ON_ERROR), 'revision' => max(1, (int) ($row['revision'] ?? 1)), 'updated_at' => now(), 'deleted_at' => null];
                    if ($exists) {
                        DB::table($table)->where('user_id', $userId)->where('id', $id)->update($values);
                    } else {
                        DB::table($table)->insert(['id' => $id, 'user_id' => $userId, ...$values, 'created_at' => now()]);
                    }
                }
            }
        });

        return response()->json(['data' => ['imported' => $preview['counts'], 'strategy' => $data['strategy']]]);
    }

    private function preview(Request $request): array
    {
        $data = $request->validate(['backup' => 'required|array', 'backup.schema_version' => 'required|integer|in:1', 'backup.created_at' => 'nullable|date']);
        $backup = $data['backup'];
        $errors = [];
        $counts = [];
        foreach (self::TABLES as $table => $kind) {
            $records = $backup[$table] ?? [];
            $counts[$table] = count($records);
            foreach ($records as $index => $row) {
                if (! is_array($row) || ! isset($row['data']) || ! is_array($row['data']) || isset($row['id']) && ! Str::isUuid($row['id'])) {
                    $errors[] = "{$table}[{$index}]";
                }
            }
        }
        foreach (array_keys($backup) as $key) {
            if ($key !== 'schema_version' && $key !== 'created_at' && ! isset(self::TABLES[$key])) {
                $errors[] = "unsupported:{$key}";
            }
        }

        return ['valid' => count($errors) === 0, 'counts' => $counts, 'errors' => $errors, 'strategy_required' => true];
    }

    private function render(string $format, array $rows, string $name): string
    {
        if ($format === 'json') {
            return json_encode(['schema_version' => 1, 'created_at' => now()->toISOString(), ...$rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        if ($format === 'csv') {
            $lines = ['title,start,end,timezone,employer,currency,estimated_minor,note'];
            foreach ($rows['shifts'] as $row) {
                $d = $row['data'];
                $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', [$d['title'] ?? 'Shift', $d['startsAt'] ?? '', $d['endsAt'] ?? '', $d['timezone'] ?? 'UTC', $d['employerId'] ?? '', $d['currency'] ?? 'RON', $d['hourlyRateMinor'] ?? 0, $d['note'] ?? '']));
            }

return implode("\r\n", $lines);
        }
        if ($format === 'ics') {
            $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TuraPlan//Shift Calendar 1.0//EN', 'CALSCALE:GREGORIAN'];
            foreach ($rows['shifts'] as $row) {
                $d = $row['data'];
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:'.($row['id'] ?? Str::uuid()).'@turaplan';
                $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTSTART:'.Carbon::parse($d['startsAt'])->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.Carbon::parse($d['endsAt'])->utc()->format('Ymd\THis\Z');
                $lines[] = 'SUMMARY:'.$this->icsEscape($d['title'] ?? 'Shift');
                $lines[] = 'END:VEVENT';
            }$lines[] = 'END:VCALENDAR';

            return implode("\r\n", $lines)."\r\n";
        }

        return Pdf::loadView('exports.calendar', ['rows' => $rows['shifts'], 'name' => $name])->setPaper('a4', 'portrait')->output();
    }

    private function icsEscape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"],['\\\\', '\\;', '\\,', '\\n'],$value);
    }
}
