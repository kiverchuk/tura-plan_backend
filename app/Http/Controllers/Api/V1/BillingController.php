<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GooglePubSubTokenVerifier;
use App\Services\StoreVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class BillingController extends Controller
{
    public function __construct(private readonly StoreVerificationService $verifier, private readonly GooglePubSubTokenVerifier $googleTokenVerifier) {}

    public function verifyApple(Request $request): JsonResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'string', 'max:191']]);

        return $this->verify($request, 'apple', fn () => $this->verifier->apple($data['transaction_id'], $request->user()->public_id));
    }

    public function verifyGoogle(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => ['required', 'string', 'max:160'], 'purchase_token' => ['required', 'string', 'max:4096']]);

        return $this->verify($request, 'google', fn () => $this->verifier->google($data['product_id'], $data['purchase_token'], hash('sha256', $request->user()->public_id)));
    }

    public function restore(Request $request): JsonResponse
    {
        $data = $request->validate(['apple_transaction_ids' => ['sometimes', 'array', 'max:100'], 'apple_transaction_ids.*' => ['string', 'max:191'], 'google_purchases' => ['sometimes', 'array', 'max:100'], 'google_purchases.*.product_id' => ['required', 'string', 'max:160'], 'google_purchases.*.purchase_token' => ['required', 'string', 'max:4096']]);
        $results = [];
        foreach ($data['apple_transaction_ids'] ?? [] as $id) {
            $response = $this->verify($request, 'apple', fn () => $this->verifier->apple($id, $request->user()->public_id));
            $results[] = $response->getData(true);
        }foreach ($data['google_purchases'] ?? [] as $purchase) {
            $response = $this->verify($request, 'google', fn () => $this->verifier->google($purchase['product_id'], $purchase['purchase_token'], hash('sha256', $request->user()->public_id)));
            $results[] = $response->getData(true);
        }

        return response()->json(['data' => ['restored' => $results]]);
    }

    public function entitlements(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('entitlements')->where('user_id', $request->user()->getKey())->where(function ($q) {
            $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
        })->get(['key', 'state', 'starts_at', 'ends_at'])]);
    }

    public function appleNotification(Request $request): JsonResponse
    {
        $signed = $request->validate(['signedPayload' => ['required', 'string']])['signedPayload'];
        try {
            $notification = $this->verifier->appleNotification($signed);
            $eventId = (string) ($notification['notificationUUID'] ?? hash('sha256', $signed));
            $existing = DB::table('store_webhook_events')->where('store', 'apple')->where('event_id', $eventId)->first();
            if ($existing && $existing->status === 'processed') {
                return response()->json(['data' => ['received' => true, 'duplicate' => true]]);
            }
            if (! $existing) {
                DB::table('store_webhook_events')->insert(['id' => (string) Str::uuid(), 'store' => 'apple', 'event_id' => $eventId, 'status' => 'received', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $signedTransaction = $notification['data']['signedTransactionInfo'] ?? null;
            if ($signedTransaction) {
                $transaction = $this->verifier->appleNotification($signedTransaction);
                $original = $transaction['originalTransactionId'] ?? null;
                if ($original) {
                    $state = match ($notification['notificationType'] ?? '') {
                        'REFUND','REVOKE' => 'refunded','EXPIRED' => 'expired','DID_FAIL_TO_RENEW' => 'grace',default => null
                    };
                    if ($state) {
                        $this->applyNotification('apple', $original, $state, isset($transaction['expiresDate']) ? now()->setTimestamp((int) ($transaction['expiresDate'] / 1000)) : null);
                    }
                }
            }
            DB::table('store_webhook_events')->where('store', 'apple')->where('event_id', $eventId)->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => ['received' => true]]);
        } catch (RuntimeException $e) {
            return $this->failure($e);
        }
    }

    public function googleNotification(Request $request): JsonResponse
    {
        $eventId = null;
        try {
            $this->googleTokenVerifier->verify($request->header('Authorization'));
            $data = $request->validate(['message.data' => ['required', 'string'], 'message.messageId' => ['nullable', 'string', 'max:191']]);
            $decoded = base64_decode($data['message']['data'], true);
            if ($decoded === false) {
                throw new RuntimeException('Google Pub/Sub message is malformed.', 422);
            }
            $notification = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
            $eventId = $data['message']['messageId'] ?? hash('sha256', $decoded);
            $existing = DB::table('store_webhook_events')->where('store', 'google')->where('event_id', $eventId)->first();
            if ($existing && $existing->status === 'processed') {
                return response()->json(['data' => ['received' => true, 'duplicate' => true]]);
            }
            if ($existing) {
                DB::table('store_webhook_events')->where('id', $existing->id)->update(['status' => 'received', 'error_code' => null, 'processed_at' => null, 'updated_at' => now()]);
            } else {
                DB::table('store_webhook_events')->insert(['id' => (string) Str::uuid(), 'store' => 'google', 'event_id' => $eventId, 'status' => 'received', 'received_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            if (isset($notification['testNotification'])) {
                DB::table('store_webhook_events')->where('store', 'google')->where('event_id', $eventId)->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);

                return response()->json(['data' => ['received' => true, 'test' => true]]);
            }
            $subscription = $notification['subscriptionNotification'] ?? null;
            if (! is_array($subscription) || ! is_string($subscription['purchaseToken'] ?? null)) {
                throw new RuntimeException('Unsupported Google Pub/Sub notification.', 422);
            }
            $original = hash('sha256', $subscription['purchaseToken']);
            $stored = DB::table('subscriptions')->where('store', 'google')->where('original_transaction_id', $original)->first();
            if ($stored) {
                $kind = (int) ($subscription['notificationType'] ?? 0);
                $state = match ($kind) {
                    1,2,4,7,8 => 'active', 5 => 'on_hold', 6 => 'grace', 9,10 => 'paused', 12 => 'revoked', 13 => 'expired', default => null
                };
                $expires = $stored->expires_at;
                if ($state && $kind !== 3) {
                    DB::table('subscriptions')->where('id', $stored->id)->update(['status' => $state, 'revoked_at' => in_array($state, ['revoked'], true) ? now() : $stored->revoked_at, 'updated_at' => now()]);
                    DB::table('entitlements')->where('user_id', $stored->user_id)->where('key', 'premium')->update(['state' => $state, 'ends_at' => $expires, 'updated_at' => now()]);
                } elseif ($kind === 3) {
                    DB::table('subscriptions')->where('id', $stored->id)->update(['status' => 'canceled', 'updated_at' => now()]);
                }
            }
            DB::table('store_webhook_events')->where('store', 'google')->where('event_id', $eventId)->update(['status' => 'processed', 'processed_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => ['received' => true]]);
        } catch (RuntimeException $e) {
            if ($eventId !== null) {
                DB::table('store_webhook_events')->where('store', 'google')->where('event_id', $eventId)->update(['status' => 'failed', 'error_code' => 'processing_failed', 'updated_at' => now()]);
            }

            return $this->failure($e);
        }
    }

    private function verify(Request $request, string $store, callable $operation): JsonResponse
    {
        try {
            $receipt = $operation();
            $owner = DB::table('subscriptions')->where('store', $store)->where('original_transaction_id', $receipt['originalTransactionId'])->first();
            if ($owner && (int) $owner->user_id !== (int) $request->user()->getKey()) {
                return response()->json(['error' => ['code' => 'purchase_already_linked', 'message' => 'This store purchase is linked to another account.']], 409);
            }
            DB::transaction(function () use ($request, $receipt) {
                $id = DB::table('subscriptions')->where('store', $receipt['store'])->where('original_transaction_id', $receipt['originalTransactionId'])->value('id') ?? (string) Str::uuid();
                DB::table('subscriptions')->updateOrInsert(['id' => $id], ['user_id' => $request->user()->getKey(), 'store' => $receipt['store'], 'product_id' => $receipt['productId'], 'original_transaction_id' => $receipt['originalTransactionId'], 'status' => $receipt['state'], 'expires_at' => $receipt['expiresAt'], 'revoked_at' => in_array($receipt['state'], ['refunded', 'revoked'], true) ? now() : null, 'verified_payload' => json_encode(['transaction_id' => $receipt['transactionId'], 'environment' => $receipt['environment']], JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now()]);
                $key = $receipt['state'] === 'active' ? 'premium' : null;
                if ($key) {
                    DB::table('entitlements')->updateOrInsert(['user_id' => $request->user()->getKey(), 'key' => $key], ['id' => DB::table('entitlements')->where('user_id', $request->user()->getKey())->where('key', $key)->value('id') ?? (string) Str::uuid(), 'state' => 'active', 'starts_at' => now(), 'ends_at' => $receipt['expiresAt'], 'updated_at' => now(), 'created_at' => now()]);
                } else {
                    DB::table('entitlements')->where('user_id', $request->user()->getKey())->where('key', 'premium')->update(['state' => $receipt['state'], 'ends_at' => $receipt['expiresAt'], 'updated_at' => now()]);
                }
            });

            return response()->json(['data' => ['store' => $store, 'status' => $receipt['state'], 'expires_at' => $receipt['expiresAt']]]);
        } catch (RuntimeException $e) {
            return $this->failure($e);
        }
    }

    private function applyNotification(string $store, string $original, string $state, mixed $expiresAt): void
    {
        $sub = DB::table('subscriptions')->where('store', $store)->where('original_transaction_id', $original)->first();
        if (! $sub) {
            return;
        }DB::table('subscriptions')->where('id', $sub->id)->update(['status' => $state, 'expires_at' => $expiresAt, 'revoked_at' => in_array($state, ['refunded', 'revoked'], true) ? now() : $sub->revoked_at, 'updated_at' => now()]);
        DB::table('entitlements')->where('user_id', $sub->user_id)->where('key', 'premium')->update(['state' => $state, 'ends_at' => $expiresAt, 'updated_at' => now()]);
    }

    private function failure(RuntimeException $e): JsonResponse
    {
        $code = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 502;

        return response()->json(['error' => ['code' => $code === 503 ? 'store_verification_unavailable' : 'store_verification_failed', 'message' => $e->getMessage()]], $code);
    }
}
