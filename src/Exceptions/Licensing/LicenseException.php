<?php

namespace Larasell\Larasell\Exceptions\Licensing;

use RuntimeException;

/**
 * larasell.dev refused to activate or renew the license, or could not be reached.
 */
class LicenseException extends RuntimeException
{
    /**
     * @param  string  $reason  the machine-readable reason, e.g. "license_expired" or "domain_not_authorized"
     */
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The Larasell license could not be validated: {$reason}.");
    }
}
