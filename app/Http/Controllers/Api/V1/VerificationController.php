<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerificationController extends Controller
{
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

return response()->json(['data' => ['sent' => true]], 202);
    }

    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash) && $request->hasValidSignature(), 403);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

return response()->json(['data' => ['verified' => true, 'deep_link' => 'turaplan://email-verified']]);
    }
}
