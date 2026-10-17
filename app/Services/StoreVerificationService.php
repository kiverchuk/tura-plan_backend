<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StoreVerificationService
{
    public function apple(string $transactionId, ?string $expectedAccountToken = null): array
    {
        $issuer = config('services.apple.issuer_id');
        $keyId = config('services.apple.key_id');
        $keyPath = config('services.apple.private_key_path');
        $bundle = config('services.apple.bundle_id');
        $root = config('services.apple.root_ca_path');
        if (! $issuer || ! $keyId || ! $keyPath || ! is_file($keyPath) || ! $bundle || ! $root || ! is_file($root)) {
            throw new RuntimeException('Apple Store verification is not configured.', 503);
        }
        $jwt = $this->appleApiToken($issuer, $keyId, file_get_contents($keyPath), $bundle);
        $host = config('services.apple.environment') === 'Sandbox' ? 'https://api.storekit-sandbox.itunes.apple.com' : 'https://api.storekit.itunes.apple.com';
        $response = Http::withToken($jwt)->acceptJson()->get($host.'/inApps/v1/transactions/'.rawurlencode($transactionId));
        if (! $response->successful()) {
            throw new RuntimeException('Apple transaction lookup failed.', 502);
        }
        $claims = $this->verifyAppleJws($response->json('signedTransactionInfo'), $root);
        if (($claims['bundleId'] ?? null) !== $bundle || ($claims['transactionId'] ?? null) !== $transactionId) {
            throw new RuntimeException('Apple transaction does not match this app or request.', 422);
        }if ($expectedAccountToken && ($claims['appAccountToken'] ?? null) !== $expectedAccountToken) {
            throw new RuntimeException('Apple purchase is not bound to this account.', 409);
        }
        $products = config('services.apple.product_ids', []);
        if ($products && ! in_array($claims['productId'] ?? null, $products, true)) {
            throw new RuntimeException('Unknown Apple product.', 422);
        }
        $expires = isset($claims['expiresDate']) ? now()->setTimestamp((int) ($claims['expiresDate'] / 1000)) : null;
        $revoked = isset($claims['revocationDate']);

        return ['store' => 'apple', 'productId' => $claims['productId'], 'transactionId' => $transactionId, 'originalTransactionId' => $claims['originalTransactionId'] ?? $transactionId, 'state' => $revoked ? 'refunded' : (($expires && $expires->isPast()) ? 'expired' : 'active'), 'expiresAt' => $expires?->toISOString(), 'environment' => $claims['environment'] ?? null];
    }

    public function google(string $productId, string $purchaseToken, ?string $expectedAccountId = null): array
    {
        $package = config('services.google_play.package_name');
        $path = config('services.google_play.service_account_path');
        if (! $package || ! $path || ! is_file($path)) {
            throw new RuntimeException('Google Play verification is not configured.', 503);
        }
        $credentials = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $token = $this->googleAccessToken($credentials);
        $response = Http::withToken($token)->acceptJson()->get('https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'.rawurlencode($package).'/purchases/subscriptionsv2/tokens/'.rawurlencode($purchaseToken));
        if (! $response->successful()) {
            throw new RuntimeException('Google Play transaction lookup failed.', 502);
        }$body = $response->json();
        $subscription = collect($body['lineItems'] ?? [])->firstWhere('productId', $productId);
        if (! $subscription) {
            throw new RuntimeException('Google Play product does not match purchase.', 422);
        }$products = config('services.google_play.product_ids', []);
        if ($products && ! in_array($productId, $products, true)) {
            throw new RuntimeException('Unknown Google Play product.', 422);
        }if ($expectedAccountId && ($body['externalAccountIdentifiers']['obfuscatedExternalAccountId'] ?? null) !== $expectedAccountId) {
            throw new RuntimeException('Google Play purchase is not bound to this account.', 409);
        }
        $expiry = $subscription['expiryTime'] ?? null;
        $state = $body['subscriptionState'] ?? '';
        $active = in_array($state, ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD', 'SUBSCRIPTION_STATE_CANCELED'], true) && (! $expiry || now()->lt($expiry));

        return ['store' => 'google', 'productId' => $productId, 'transactionId' => $body['latestOrderId'] ?? hash('sha256', $purchaseToken), 'originalTransactionId' => hash('sha256', $purchaseToken), 'state' => $active ? 'active' : (($state === 'SUBSCRIPTION_STATE_EXPIRED') ? 'expired' : 'revoked'), 'expiresAt' => $expiry, 'environment' => 'Production'];
    }

    private function appleApiToken(string $issuer, string $keyId, string $privateKey, string $bundle): string
    {
        $now = time();
        $head = $this->b64(json_encode(['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body = $this->b64(json_encode(['iss' => $issuer, 'iat' => $now, 'exp' => $now + 300, 'aud' => 'appstoreconnect-v1', 'bid' => $bundle], JSON_THROW_ON_ERROR));
        $input = $head.'.'.$body;
        $key = openssl_pkey_get_private($privateKey);
        if (! $key || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Apple signing key could not sign a request.', 503);
        }

return $input.'.'.$this->b64($this->derToJose($der));
    }

    private function verifyAppleJws(string $jws, string $rootPath): array
    {
        $parts = explode('.', $jws);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid Apple signed transaction.', 422);
        }$header = json_decode($this->unb64($parts[0]), true, 512, JSON_THROW_ON_ERROR);
        if (($header['alg'] ?? null) !== 'ES256' || empty($header['x5c'][0])) {
            throw new RuntimeException('Unsupported Apple signature.', 422);
        }
        $certs = array_map(fn ($der) => "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode(base64_decode($der, true)), 64, "\n")."-----END CERTIFICATE-----\n", $header['x5c']);
        $root = file_get_contents($rootPath);
        $chain = [...$certs, $root];
        foreach ($chain as $pem) {
            $parsed = openssl_x509_parse($pem);
            if (! $parsed || ($parsed['validFrom_time_t'] ?? 0) > time() || ($parsed['validTo_time_t'] ?? 0) < time()) {
                throw new RuntimeException('Apple certificate is invalid or expired.', 422);
            }
        }
        for ($i = 0; $i < count($certs) - 1; $i++) {
            $issuerKey = openssl_pkey_get_public($certs[$i + 1]);
            if (! $issuerKey || openssl_x509_verify($certs[$i], $issuerKey) !== 1) {
                throw new RuntimeException('Apple certificate chain is invalid.', 422);
            }
        }
        $rootCert = openssl_x509_read($root);
        $last = $certs[count($certs) - 1];
        $trusted = openssl_x509_fingerprint($last, 'sha256') === openssl_x509_fingerprint($rootCert, 'sha256');
        if (! $trusted) {
            $rootKey = openssl_pkey_get_public($rootCert);
            $trusted = $rootKey && openssl_x509_verify($last, $rootKey) === 1;
        }
        if (! $trusted) {
            throw new RuntimeException('Apple certificate chain is not rooted in the configured trust certificate.', 422);
        }
        $leafKey = openssl_pkey_get_public($certs[0]);
        $signature = $this->joseToDer($this->unb64($parts[2]));
        if (! $leafKey || openssl_verify($parts[0].'.'.$parts[1], $signature, $leafKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Apple transaction signature is invalid.', 422);
        }

        return json_decode($this->unb64($parts[1]), true, 512, JSON_THROW_ON_ERROR);
    }

    public function appleNotification(string $signedPayload): array
    {
        $root = config('services.apple.root_ca_path');
        if (! $root || ! is_file($root)) {
            throw new RuntimeException('Apple notification verification is not configured.', 503);
        }

return $this->verifyAppleJws($signedPayload, $root);
    }

    private function googleAccessToken(array $credentials): string
    {
        $uri = $credentials['token_uri'] ?? null;
        $email = $credentials['client_email'] ?? null;
        $private = $credentials['private_key'] ?? null;
        if (! $uri || ! $email || ! $private) {
            throw new RuntimeException('Google service-account credentials are incomplete.', 503);
        }
        $now = time();
        $head = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claim = $this->b64(json_encode(['iss' => $email, 'scope' => 'https://www.googleapis.com/auth/androidpublisher', 'aud' => $uri, 'iat' => $now, 'exp' => $now + 3600], JSON_THROW_ON_ERROR));
        $input = $head.'.'.$claim;
        if (! openssl_sign($input, $signature, $private, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Google signing key could not sign a request.', 503);
        }$assertion = $input.'.'.$this->b64($signature);
        $response = Http::asForm()->post($uri, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion]);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new RuntimeException('Google OAuth token request failed.', 503);
        }

return $response->json('access_token');
    }

    private function derToJose(string $der): string
    {
        $offset = 2;
        if (ord($der[1]) > 0x80) {
            $offset = 2 + (ord($der[1]) & 0x7F);
        }if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA signature.');
        }$rLen = ord($der[$offset++]);
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA signature.');
        }$sLen = ord($der[$offset++]);
        $s = substr($der, $offset, $sLen);
        $r = ltrim($r, "\0");
        $s = ltrim($s, "\0");

        return str_pad($r, 32, "\0", STR_PAD_LEFT).str_pad($s, 32, "\0", STR_PAD_LEFT);
    }

    private function joseToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new RuntimeException('Invalid ES256 signature length.', 422);
        }$items = '';
        foreach ([substr($raw, 0, 32), substr($raw, 32, 32)] as $part) {
            $part = ltrim($part, "\0");
            if ($part === '' || ord($part[0]) >= 0x80) {
                $part = "\0".$part;
            }$items .= "\x02".chr(strlen($part)).$part;
        }

return "\x30".chr(strlen($items)).$items;
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value),'+/','-_'),'=');
    }

    private function unb64(string $value): string
    {
        $decoded = base64_decode(strtr($value,'-_','+/').str_repeat('=',(4 - strlen($value) % 4) % 4),true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url value.',422);
        }

return $decoded;
    }
}
