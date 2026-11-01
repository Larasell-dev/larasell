<?php

namespace Larasell\Larasell\Licensing;

use Carbon\CarbonImmutable;
use Larasell\Larasell\Enums\Plan;

/**
 * A lease larasell.dev signed for this installation: proof, checked offline, that the
 * installation's license grants a plan until the lease expires.
 *
 * Leases are short-lived (14 days) and renewed daily, so a license that expires, is
 * suspended or revoked stops granting its plan once the last lease runs out. That window
 * is the grace period of the license agreement.
 */
final readonly class Lease
{
    private function __construct(
        public string $licenseId,
        public string $activationId,
        public Plan $plan,
        public string $domain,
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * The lease in the token, or null when the token is not a valid, unexpired lease
     * larasell.dev signed for the given domain.
     *
     * @param  string  $publicKey  the base64-encoded Ed25519 public key of larasell.dev
     */
    public static function verify(string $token, string $publicKey, string $domain): ?self
    {
        $parts = explode('.', $token);
        $key = base64_decode($publicKey, true);

        if (count($parts) !== 3 || $key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        [$header, $payload, $signature] = $parts;
        $signature = self::base64UrlDecode($signature);

        if ($signature === null
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $header.'.'.$payload, $key)) {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($payload) ?? '', true);

        if (! is_array($claims)
            || ($claims['iss'] ?? null) !== 'larasell'
            || ($claims['product'] ?? null) !== 'larasell'
            || ! is_string($claims['license_id'] ?? null)
            || ! is_string($claims['activation_id'] ?? null)
            || ! is_string($claims['domain'] ?? null)
            || ! is_int($claims['exp'] ?? null)
            || ! is_string($claims['plan'] ?? null)) {
            return null;
        }

        $plan = Plan::tryFrom($claims['plan']);
        $expiresAt = CarbonImmutable::createFromTimestampUTC($claims['exp']);
        $domain = strtolower($domain);

        if ($plan === null
            || $expiresAt->isPast()
            || ($claims['domain'] !== $domain && 'www.'.$claims['domain'] !== $domain)) {
            return null;
        }

        return new self($claims['license_id'], $claims['activation_id'], $plan, $claims['domain'], $expiresAt);
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
