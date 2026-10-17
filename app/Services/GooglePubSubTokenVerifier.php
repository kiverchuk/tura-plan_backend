<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GooglePubSubTokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(?string $authorization): array
    {
        $expectedAudience = config('services.google_play.pubsub_audience');
        $expectedEmail = config('services.google_play.pubsub_service_account');
        if (! $expectedAudience || ! $expectedEmail || ! $authorization || ! str_starts_with($authorization, 'Bearer ')) {
            throw new RuntimeException('Google Pub/Sub OIDC is not configured.', 503);
        }

        $jwt = substr($authorization, 7);
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid Google Pub/Sub token.', 401);
        }
        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
        $header = json_decode($this->decode($encodedHeader), true, 512, JSON_THROW_ON_ERROR);
        $claims = json_decode($this->decode($encodedClaims), true, 512, JSON_THROW_ON_ERROR);
        if (($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw new RuntimeException('Unsupported Google Pub/Sub token signature.', 401);
        }

        $keys = Cache::remember('google_oidc_jwks', now()->addHour(), function (): array {
            $response = Http::timeout(5)->get('https://www.googleapis.com/oauth2/v3/certs');
            if (! $response->successful()) {
                throw new RuntimeException('Google OIDC keys are unavailable.', 503);
            }

            return $response->json('keys', []);
        });
        $jwk = collect($keys)->firstWhere('kid', $header['kid']);
        if (! is_array($jwk) || ! isset($jwk['n'], $jwk['e'])) {
            Cache::forget('google_oidc_jwks');
            throw new RuntimeException('Google Pub/Sub signing key was not found.', 401);
        }
        $publicKey = openssl_pkey_get_public($this->jwkToPem($jwk));
        $signature = $this->decode($encodedSignature);
        if (! $publicKey || openssl_verify($encodedHeader.'.'.$encodedClaims, $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Google Pub/Sub token signature is invalid.', 401);
        }

        $now = time();
        $issuerValid = in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true);
        if (! $issuerValid || ($claims['aud'] ?? null) !== $expectedAudience || ($claims['email'] ?? null) !== $expectedEmail || ($claims['email_verified'] ?? false) !== true || (int) ($claims['exp'] ?? 0) <= $now || (int) ($claims['iat'] ?? 0) > $now + 60) {
            throw new RuntimeException('Google Pub/Sub token claims are invalid.', 401);
        }

        return $claims;
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Malformed Google Pub/Sub token.', 401);
        }

        return $decoded;
    }

    /** @param array<string, string> $jwk */
    private function jwkToPem(array $jwk): string
    {
        $modulus = $this->decode($jwk['n']);
        $exponent = $this->decode($jwk['e']);
        $rsa = $this->sequence($this->integer($modulus).$this->integer($exponent));
        $algorithm = hex2bin('300d06092a864886f70d0101010500');

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($this->sequence($algorithm.$this->bitString($rsa))), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function integer(string $value): string
    {
        if (ord($value[0]) > 0x7F) {
            $value = "\0".$value;
        }

        return "\x02".$this->length(strlen($value)).$value;
    }

    private function bitString(string $value): string
    {
        $value = "\0".$value;

        return "\x03".$this->length(strlen($value)).$value;
    }

    private function sequence(string $value): string
    {
        return "\x30".$this->length(strlen($value)).$value;
    }

    private function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\0");

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
