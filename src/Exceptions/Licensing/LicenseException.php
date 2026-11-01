<?php

namespace Larasell\Larasell\Exceptions\Licensing;

use Larasell\Chronicle\HasStructuredError;
use Larasell\Chronicle\StructuredError;
use RuntimeException;

/**
 * larasell.dev refused to activate or renew the license, or could not be reached.
 */
class LicenseException extends RuntimeException implements StructuredError
{
    use HasStructuredError;

    /**
     * @param  string  $reason  the machine-readable reason larasell.dev answered with, e.g. "license_expired"
     */
    public static function refused(string $reason): static
    {
        [$why, $fix] = match ($reason) {
            'invalid_license' => ['larasell.dev knows no license with this key.', 'Copy the key again from your larasell.dev dashboard.'],
            'domain_not_authorized' => ['The license does not list the domain in APP_URL.', 'Add the domain to the license on your larasell.dev dashboard, or correct APP_URL.'],
            'invalid_domain' => ['APP_URL has no valid host name.', 'Set APP_URL to the address of the shop, e.g. https://shop.example.com.'],
            'activation_limit_reached' => ['Every activation the license allows is in use.', 'Deactivate an installation on your larasell.dev dashboard, or raise the activation limit.'],
            'license_expired' => ['The license is past its expiry date.', 'Renew the license on your larasell.dev dashboard.'],
            'license_not_started' => ['The license starts at a later date.', 'Activate it again once it has started.'],
            'license_suspended', 'license_revoked' => ['The license is no longer valid.', 'Contact nils@larasell.dev.'],
            'invalid_activation' => ['larasell.dev no longer knows this installation, e.g. because it was deactivated.', 'Activate it again with larasell:license:activate.'],
            default => ["larasell.dev answered with [{$reason}].", 'Try again later, or contact nils@larasell.dev.'],
        };

        return static::structured(
            code: 'larasell.licensing.'.str_replace('_', '-', $reason),
            message: "larasell.dev refused the license: {$reason}.",
            why: $why,
            fix: $fix,
        );
    }

    public static function unavailable(int $status): static
    {
        return static::structured(
            code: 'larasell.licensing.unavailable',
            message: "larasell.dev could not check the license (HTTP {$status}).",
            why: 'The license API failed or answered without a reason.',
            fix: 'Try again later. The shop keeps its current lease until it expires.',
        );
    }

    public static function invalidLease(): static
    {
        return static::structured(
            code: 'larasell.licensing.invalid-lease',
            message: 'The lease from larasell.dev is not valid for this shop.',
            why: 'Its signature, domain or expiry did not check out, so it may be forged or meant for another shop.',
            fix: 'Check that APP_URL is the domain the license was activated for.',
        );
    }

    public static function notActivated(): static
    {
        return static::structured(
            code: 'larasell.licensing.not-activated',
            message: 'This installation has no activated license to renew.',
            why: 'larasell:license:activate has not run here, or its activation was lost.',
            fix: 'Run php artisan larasell:license:activate with the key from your larasell.dev dashboard.',
        );
    }
}
