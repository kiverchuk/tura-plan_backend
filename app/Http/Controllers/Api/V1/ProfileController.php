<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->data($request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'locale' => ['sometimes', 'in:en,ro,ru,uk,de,fr,es'], 'region' => ['sometimes', 'string', 'max:16']]);
        $request->user()->fill($data)->save();

        return response()->json(['data' => $this->data($request)]);
    }

    private function data(Request $request): array
    {
        $user = $request->user();

        return ['id' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'email_verified' => $user->hasVerifiedEmail(), 'locale' => $user->locale, 'region' => $user->region];
    }
}
