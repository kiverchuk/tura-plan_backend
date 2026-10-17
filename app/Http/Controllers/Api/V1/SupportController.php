<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SupportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['category' => 'required|in:account,billing,calendar,privacy,bug,other', 'subject' => 'required|string|max:160', 'message' => 'required|string|max:4000']);
        $id = (string) Str::uuid();
        DB::table('support_tickets')->insert(['id' => $id, 'user_id' => $request->user()->getKey(), 'category' => $data['category'], 'status' => 'open', 'subject' => $data['subject'], 'message' => $data['message'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'status' => 'open']], 201);
    }
}
