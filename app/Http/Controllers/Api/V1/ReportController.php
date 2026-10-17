<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportController extends Controller
{
    public function hours(Request $request): JsonResponse
    {
        return $this->report($request, 'hours');
    }

    public function earnings(Request $request): JsonResponse
    {
        return $this->report($request, 'earnings');
    }

    public function overtime(Request $request): JsonResponse
    {
        return $this->report($request, 'overtime');
    }

    private function report(Request $request, string $kind): JsonResponse
    {
        $filters = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after:from'], 'employer' => ['nullable', 'uuid'], 'currency' => ['nullable', 'regex:/^[A-Z]{3}$/']]);
        $query = DB::table('shifts')->where('user_id', $request->user()->getKey())->whereNull('deleted_at');
        if (isset($filters['employer'])) {
            $query->where('data->employerId', $filters['employer']);
        }
        $shifts = $query->where('data->startsAt', '>=', Carbon::parse($filters['from'])->toISOString())->where('data->startsAt', '<', Carbon::parse($filters['to'])->toISOString())->get();
        $minutes = 0;
        $money = [];
        $employers = [];
        $overtimeMinutes = 0;
        foreach ($shifts as $row) {
            $data = json_decode($row->data, true, 512, JSON_THROW_ON_ERROR);
            $start = Carbon::parse($data['startsAt'] ?? '');
            $end = Carbon::parse($data['endsAt'] ?? '');
            $elapsed = max(0, $start->diffInMinutes($end));
            $paid = max(0, $elapsed - (int) ($data['breakMinutes'] ?? 0));
            $minutes += $paid;
            $employer = (string) ($data['employerId'] ?? 'none');
            $employers[$employer] = ($employers[$employer] ?? 0) + $paid;
            $currency = $data['currency'] ?? 'RON';
            $rate = (int) ($data['hourlyRateMinor'] ?? 0);
            if (($filters['currency'] ?? $currency) === $currency) {
                $money[$currency] = ($money[$currency] ?? 0) + intdiv($rate * $paid + 30, 60);
            }
            if ($start->isSameDay($end) && $paid > 8 * 60) {
                $overtimeMinutes += $paid - 8 * 60;
            }
        }
        $data = match ($kind) {
            'hours' => ['minutes' => $minutes, 'hours' => round($minutes / 60, 2), 'by_employer' => $employers],'earnings' => ['estimated_minor_by_currency' => $money, 'warning' => 'Estimate only; not tax or payroll advice.'],'overtime' => ['overtime_minutes' => $overtimeMinutes, 'rule' => 'User-configured daily thresholds are not set; default 8-hour daily threshold is informational.']
        };

        return response()->json(['data' => ['from' => $filters['from'], 'to' => $filters['to'], 'currency_filter' => $filters['currency'] ?? null, 'shift_count' => $shifts->count(), ...$data]]);
    }
}
