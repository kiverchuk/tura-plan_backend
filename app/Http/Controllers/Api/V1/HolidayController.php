<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['country' => ['nullable', 'size:2'], 'region' => ['nullable', 'string', 'max:32'], 'year' => ['nullable', 'integer', 'min:2000', 'max:2200'], 'locale' => ['nullable', 'in:en,ro,ru,uk,de,fr,es']]);
        $query = DB::table('holiday_packs')->where('published', true);
        foreach (['country', 'region', 'year', 'locale'] as $key) {
            if (isset($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

return response()->json(['data' => $query->orderByDesc('year')->limit(100)->get()]);
    }

    public function show(string $id): JsonResponse
    {
        $pack = DB::table('holiday_packs')->where('id', $id)->where('published', true)->first();
        abort_if(! $pack, 404);
        $entries = DB::table('holiday_entries')->where('holiday_pack_id', $id)->orderBy('date')->get();

        return response()->json(['data' => ['pack' => $pack, 'entries' => $entries]]);
    }
}
