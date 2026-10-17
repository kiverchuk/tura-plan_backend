<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        return User::factory()->create(['public_id' => (string) Str::uuid(), 'email_verified_at' => now()]);
    }

    private function premium(User $user): void
    {
        DB::table('entitlements')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'key' => 'premium', 'state' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_free_accounts_cannot_access_cloud_calendar_and_premium_accounts_can(): void
    {
        $user = $this->account();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/shifts')->assertForbidden()->assertJsonPath('error.code', 'premium_required');
        $this->premium($user);
        $this->getJson('/api/v1/shifts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_users_cannot_read_another_accounts_calendar_record(): void
    {
        $owner = $this->account();
        $other = $this->account();
        $this->premium($owner);
        $this->premium($other);
        $id = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $id, 'user_id' => $owner->id, 'data' => json_encode(['title' => 'Private shift', 'startsAt' => '2026-10-17T09:00:00Z', 'endsAt' => '2026-10-17T17:00:00Z']), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/shifts/{$id}")->assertNotFound();
        $this->getJson('/api/v1/shifts')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_calendar_update_requires_current_revision(): void
    {
        $user = $this->account();
        $this->premium($user);
        Sanctum::actingAs($user);
        $created = $this->postJson('/api/v1/shifts', ['data' => ['title' => 'Day', 'startsAt' => '2026-10-17T09:00:00Z', 'endsAt' => '2026-10-17T17:00:00Z', 'timezone' => 'Europe/Chisinau', 'currency' => 'RON']])->assertCreated()->json('data');
        $this->patchJson('/api/v1/shifts/'.$created['id'], ['revision' => 1, 'data' => ['title' => 'Updated']])->assertOk()->assertJsonPath('data.revision', 2);
        $this->patchJson('/api/v1/shifts/'.$created['id'], ['revision' => 1, 'data' => ['title' => 'Stale edit']])->assertStatus(409)->assertJsonPath('error.code', 'revision_conflict');
    }

    public function test_sync_push_replays_are_idempotent_and_cursor_is_monotonic(): void
    {
        $user = $this->account();
        $this->premium($user);
        Sanctum::actingAs($user);
        $mutation = (string) Str::uuid();
        $entity = (string) Str::uuid();
        $body = ['mutations' => [['id' => $mutation, 'entityType' => 'shift', 'entityId' => $entity, 'operation' => 'upsert', 'payload' => ['title' => 'Night', 'startsAt' => '2026-10-17T18:00:00Z', 'endsAt' => '2026-10-18T02:00:00Z', 'timezone' => 'Europe/Chisinau']]]];
        $this->postJson('/api/v1/sync/push', $body, ['Idempotency-Key' => 'night-shift-write-01'])->assertOk()->assertJsonPath('data.applied.0.status', 'applied');
        $this->postJson('/api/v1/sync/push', $body, ['Idempotency-Key' => 'night-shift-write-01'])->assertOk()->assertJsonPath('data.applied.0.status', 'applied');
        $this->postJson('/api/v1/sync/push', $body, ['Idempotency-Key' => 'night-shift-retry-02'])->assertOk()->assertJsonPath('data.applied.0.status', 'duplicate');
        self::assertSame(1, DB::table('shifts')->where('user_id', $user->id)->count());
        $pull = $this->getJson('/api/v1/sync/pull?cursor=0')->assertOk()->assertJsonCount(1, 'data.changes')->json('data');
        $this->getJson('/api/v1/sync/pull?cursor='.$pull['cursor'])->assertOk()->assertJsonCount(0, 'data.changes');
    }

    public function test_share_projection_never_exposes_pay_or_private_notes(): void
    {
        $user = $this->account();
        $this->premium($user);
        Sanctum::actingAs($user);
        $shift = (string) Str::uuid();
        DB::table('shifts')->insert(['id' => $shift, 'user_id' => $user->id, 'data' => json_encode(['title' => 'Evening', 'startsAt' => '2026-10-17T09:00:00Z', 'endsAt' => '2026-10-17T17:00:00Z', 'hourlyRateMinor' => 99999, 'currency' => 'RON', 'note' => 'private note']), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $created = $this->postJson('/api/v1/shares', ['scope' => ['from' => '2026-10-01', 'to' => '2026-11-01'], 'expires_at' => now()->addDay()->toISOString()])->assertCreated()->json('data');
        $token = basename(parse_url($created['url'], PHP_URL_PATH));
        $shared = $this->getJson('/api/v1/shared/'.$token)->assertOk()->json('data');
        $encoded = json_encode($shared);
        self::assertStringNotContainsString('hourlyRateMinor', $encoded);
        self::assertStringNotContainsString('private note', $encoded);
        self::assertStringContainsString('Evening', $encoded);
        $this->deleteJson('/api/v1/shares/'.$created['id'])->assertNoContent();
        $this->getJson('/api/v1/shared/'.$token)->assertNotFound();
    }

    public function test_store_purchase_is_not_granted_without_server_credentials(): void
    {
        config(['services.apple.issuer_id' => null, 'services.apple.key_id' => null, 'services.apple.private_key_path' => null, 'services.apple.bundle_id' => null, 'services.apple.root_ca_path' => null]);
        $user = $this->account();
        $this->premium($user);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/billing/apple/verify', ['transaction_id' => 'test-transaction'])->assertStatus(503)->assertJsonPath('error.code', 'store_verification_unavailable');
        self::assertSame(0, DB::table('entitlements')->where('user_id', $user->id)->count());
    }

    public function test_sync_conflict_is_persisted_and_can_be_resolved_with_local_data(): void
    {
        $user = $this->account();
        $this->premium($user);
        Sanctum::actingAs($user);
        $shift = $this->postJson('/api/v1/shifts', ['data' => ['title' => 'Original', 'startsAt' => '2026-10-17T09:00:00Z', 'endsAt' => '2026-10-17T17:00:00Z', 'timezone' => 'Europe/Chisinau', 'currency' => 'RON']])->assertCreated()->json('data');
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', ['mutations' => [[
            'id' => (string) Str::uuid(), 'entityType' => 'shift', 'entityId' => $shift['id'], 'operation' => 'upsert', 'payload' => ['title' => 'Remote edit', 'startsAt' => '2026-10-17T09:00:00Z', 'endsAt' => '2026-10-17T17:00:00Z', 'timezone' => 'Europe/Chisinau', 'currency' => 'RON'], 'baseRevision' => 1,
        ]]])->assertOk()->assertJsonPath('data.applied.0.revision', 2);

        $conflictId = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', ['mutations' => [[
            'id' => $conflictId, 'entityType' => 'shift', 'entityId' => $shift['id'], 'operation' => 'upsert', 'payload' => ['title' => 'Local edit', 'startsAt' => '2026-10-17T09:00:00Z', 'endsAt' => '2026-10-17T17:00:00Z', 'timezone' => 'Europe/Chisinau', 'currency' => 'RON'], 'baseRevision' => 1,
        ]]])->assertOk()->assertJsonPath('data.conflicts.0.id', $conflictId);
        self::assertSame('pending', DB::table('sync_conflicts')->where('id', $conflictId)->value('status'));

        $this->postJson('/api/v1/sync/conflicts/'.$conflictId.'/resolve', ['strategy' => 'local'])->assertOk()->assertJsonPath('data.applied.0.revision', 3);
        self::assertSame('Local edit', json_decode(DB::table('shifts')->where('id', $shift['id'])->value('data'), true)['title']);
        self::assertSame('resolved', DB::table('sync_conflicts')->where('id', $conflictId)->value('status'));
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/sync/push', ['mutations' => [[
            'id' => $conflictId, 'entityType' => 'shift', 'entityId' => $shift['id'], 'operation' => 'upsert', 'payload' => ['title' => 'Local edit'], 'baseRevision' => 1,
        ]]])->assertOk()->assertJsonPath('data.applied.0.status', 'duplicate');
    }

    public function test_reports_return_paid_minutes_estimated_earnings_and_overtime(): void
    {
        $user = $this->account();
        Sanctum::actingAs($user);
        DB::table('shifts')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'data' => json_encode(['title' => 'Long shift', 'startsAt' => '2026-10-17T08:00:00Z', 'endsAt' => '2026-10-17T18:00:00Z', 'breakMinutes' => 30, 'hourlyRateMinor' => 1200, 'currency' => 'RON']), 'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/reports/hours?from=2026-10-17&to=2026-10-18')->assertOk()->assertJsonPath('data.minutes', 570)->assertJsonPath('data.hours', 9.5);
        $this->getJson('/api/v1/reports/earnings?from=2026-10-17&to=2026-10-18')->assertOk()->assertJsonPath('data.estimated_minor_by_currency.RON', 11400);
        $this->getJson('/api/v1/reports/overtime?from=2026-10-17&to=2026-10-18')->assertOk()->assertJsonPath('data.overtime_minutes', 90);
    }

    public function test_google_pubsub_webhook_requires_and_verifies_google_oidc_token(): void
    {
        config(['services.google_play.pubsub_audience' => null, 'services.google_play.pubsub_service_account' => null]);
        $this->postJson('/api/v1/billing/google/notifications', [])->assertStatus(503);

        $private = openssl_pkey_get_private(file_get_contents(__DIR__.'/../Fixtures/google-pubsub-test-key.pem'));
        self::assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $keyId = 'test-google-key';
        Http::fake(['https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
            'kid' => $keyId,
            'kty' => 'RSA',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]]], 200)]);
        Cache::forget('google_oidc_jwks');
        config(['services.google_play.pubsub_audience' => 'https://api.example.test/api/v1/billing/google/notifications', 'services.google_play.pubsub_service_account' => 'turaplan-pubsub@example.iam.gserviceaccount.com']);
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'kid' => $keyId], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url(json_encode(['iss' => 'https://accounts.google.com', 'aud' => config('services.google_play.pubsub_audience'), 'email' => config('services.google_play.pubsub_service_account'), 'email_verified' => true, 'iat' => $now, 'exp' => $now + 300], JSON_THROW_ON_ERROR));
        openssl_sign($header.'.'.$claims, $signature, $private, OPENSSL_ALGO_SHA256);
        $jwt = $header.'.'.$claims.'.'.$this->base64Url($signature);
        $this->withHeader('Authorization', 'Bearer '.$jwt)->postJson('/api/v1/billing/google/notifications', [
            'message' => ['messageId' => 'google-test-message-1', 'data' => base64_encode(json_encode(['testNotification' => ['version' => '1.0']], JSON_THROW_ON_ERROR))],
        ])->assertOk()->assertJsonPath('data.test', true);
        self::assertSame('processed', DB::table('store_webhook_events')->where('event_id', 'google-test-message-1')->value('status'));
        $this->withHeader('Authorization', 'Bearer '.$jwt)->postJson('/api/v1/billing/google/notifications', [
            'message' => ['messageId' => 'google-test-message-1', 'data' => base64_encode(json_encode(['testNotification' => ['version' => '1.0']], JSON_THROW_ON_ERROR))],
        ])->assertOk()->assertJsonPath('data.duplicate', true);
        DB::table('store_webhook_events')->insert(['id' => (string) Str::uuid(), 'store' => 'google', 'event_id' => 'google-pending-retry', 'status' => 'received', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->withHeader('Authorization', 'Bearer '.$jwt)->postJson('/api/v1/billing/google/notifications', [
            'message' => ['messageId' => 'google-pending-retry', 'data' => base64_encode(json_encode(['testNotification' => ['version' => '1.0']], JSON_THROW_ON_ERROR))],
        ])->assertOk()->assertJsonPath('data.test', true);
        self::assertSame('processed', DB::table('store_webhook_events')->where('event_id', 'google-pending-retry')->value('status'));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
