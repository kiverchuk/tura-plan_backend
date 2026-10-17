<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Sanctum\PersonalAccessToken;

final class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:191', 'unique:users,email'], 'password' => ['required', 'confirmed', PasswordRule::defaults()], 'locale' => ['nullable', 'in:en,ro,ru,uk,de,fr,es'], 'region' => ['nullable', 'string', 'max:16']]);
        $user = User::query()->create(['name' => $data['name'], 'email' => Str::lower($data['email']), 'password' => Hash::make($data['password']), 'public_id' => (string) Str::uuid(), 'locale' => $data['locale'] ?? 'en', 'region' => $data['region'] ?? 'RO']);
        event(new Registered($user));

        return response()->json(['data' => ['user' => $this->publicUser($user), ...$this->issueTokens($user, $request)]], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'device_name' => ['nullable', 'string', 'max:120']]);
        $user = User::query()->where('email', Str::lower($data['email']))->first();
        if (! $user || $user->erasure_requested_at || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => ['code' => 'invalid_credentials', 'message' => 'The supplied credentials are invalid.']], 422);
        }

        return response()->json(['data' => ['user' => $this->publicUser($user), ...$this->issueTokens($user, $request, $data['device_name'] ?? 'Mobile device')]]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $data = $request->validate(['refresh_token' => ['required', 'string']]);
        [$id, $secret] = array_pad(explode('|', $data['refresh_token'], 2), 2, null);
        $token = $id ? PersonalAccessToken::query()->find($id) : null;
        if (! $token || ! $secret || ! hash_equals($token->token, hash('sha256', $secret)) || ! in_array('refresh', $token->abilities, true) || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['error' => ['code' => 'invalid_refresh_token', 'message' => 'Refresh session is no longer valid.']], 401);
        }
        $user = $token->tokenable;
        $token->delete();
        if ($user->erasure_requested_at) {
            return response()->json(['error' => ['code' => 'account_erasure_pending', 'message' => 'This account is being erased.']], 410);
        }

        return response()->json(['data' => ['user' => $this->publicUser($user), ...$this->issueTokens($user, $request, $token->name)]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        $refresh = $request->input('refresh_token');
        if (is_string($refresh) && str_contains($refresh, '|')) {
            [$id, $secret] = explode('|', $refresh, 2);
            $token = PersonalAccessToken::query()->find($id);
            if ($token && hash_equals($token->token, hash('sha256', $secret)) && in_array('refresh', $token->abilities, true)) {
                $token->delete();
            }
        }

        return response()->json(null, 204);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['data' => ['verified' => true]]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => Str::lower($request->string('email')->toString())]);

        return response()->json(['data' => ['accepted' => true]], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string'], 'password' => ['required', 'confirmed', PasswordRule::defaults()]]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['error' => ['code' => $status, 'message' => 'Unable to reset password.']], 422);
        }

        return response()->json(null, 204);
    }

    private function issueTokens(User $user, Request $request, string $deviceName = 'Mobile device'): array
    {
        $device = $request->input('device_id');
        $deviceId = is_string($device) && Str::isUuid($device) ? $device : (string) Str::uuid();
        DB::table('devices')->updateOrInsert(['id' => $deviceId, 'user_id' => $user->getKey()], ['name' => $deviceName, 'platform' => $request->input('platform', 'unknown'), 'app_version' => $request->input('app_version'), 'last_seen_at' => now(), 'revoked_at' => null, 'updated_at' => now(), 'created_at' => now()]);
        $access = $user->createToken($deviceId, ['access'], now()->addMinutes(15));
        $refresh = $user->createToken($deviceId, ['refresh'], now()->addDays(30));

        return ['access_token' => $access->plainTextToken, 'access_expires_at' => now()->addMinutes(15)->toISOString(), 'refresh_token' => $refresh->plainTextToken, 'refresh_expires_at' => now()->addDays(30)->toISOString(), 'device_id' => $deviceId];
    }

    private function publicUser(User $user): array
    {
        return ['id' => $user->public_id, 'name' => $user->name, 'email' => $user->email, 'email_verified' => $user->hasVerifiedEmail(), 'locale' => $user->locale, 'region' => $user->region];
    }
}
