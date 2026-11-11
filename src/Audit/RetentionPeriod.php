<?php

namespace Larasell\Larasell\Audit;

use Larasell\Larasell\Audit\Exceptions\InvalidRetentionPeriod;

/**
 * How many days audit records are kept: a whole number above 0.
 *
 * @internal
 */
final readonly class RetentionPeriod
{
    /**
     * @throws InvalidRetentionPeriod
     */
    public function __construct(public int $days)
    {
        if ($days < 1) {
            throw InvalidRetentionPeriod::days($days);
        }
    }

    /**
     * The period set in config/larasell/audit.php, or null to keep records forever.
     *
     * @throws InvalidRetentionPeriod
     */
    public static function fromConfig(mixed $days): ?self
    {
        if ($days === null || $days === '') {
            return null;
        }

        $whole = is_int($days) || is_string($days) && preg_match('/^-?[0-9]+$/', $days) === 1;

        if ($whole) {
            return new self((int) $days);
        }

        throw InvalidRetentionPeriod::days($days);
    }
}
