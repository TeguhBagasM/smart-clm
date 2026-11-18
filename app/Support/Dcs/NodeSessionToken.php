<?php

namespace App\Support\Dcs;

/**
 * Verifies the session JWT issued by the legacy Node application (HS256,
 * claims `sub`, `tenantId`, `role`, `exp`). Verification only, so no JWT
 * library is needed.
 *
 * Node signs two kinds of token with the same secret. The short-lived 2FA
 * challenge token carries a `purpose` claim and only proves the password step;
 * it must never act as a session, so any token with `purpose` is rejected.
 */
class NodeSessionToken
{
    public function __construct(private ?string $secret) {}

    /**
     * @return array{sub: string, tenantId: string}|null the claims, or null when the token is not a valid session token
     */
    public function verify(?string $token): ?array
    {
        if ($this->secret === null || $this->secret === '' || $token === null) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = $this->decodeJson($encodedHeader);
        $payload = $this->decodeJson($encodedPayload);
        $signature = $this->base64UrlDecode($encodedSignature);

        if ($header === null || $payload === null || $signature === null || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $expected = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret, true);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        if (! is_int($payload['exp'] ?? null) || $payload['exp'] <= time() || array_key_exists('purpose', $payload)) {
            return null;
        }

        if (! is_string($payload['sub'] ?? null) || $payload['sub'] === '' || ! is_string($payload['tenantId'] ?? null)) {
            return null;
        }

        return ['sub' => $payload['sub'], 'tenantId' => $payload['tenantId']];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $segment): ?array
    {
        $json = $this->base64UrlDecode($segment);
        $decoded = $json === null ? null : json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
