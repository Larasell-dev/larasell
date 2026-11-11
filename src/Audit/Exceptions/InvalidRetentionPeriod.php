<?php

namespace Larasell\Larasell\Audit\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

/**
 * The audit log's retention period isn't a whole number of days above 0.
 *
 * @public Catch it; only the audit log creates it, so its named constructors are internal.
 */
class InvalidRetentionPeriod extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    /**
     * @internal
     */
    public static function days(mixed $days): static
    {
        $given = var_export($days, true);

        return static::structured(
            errorCode: 'larasell.audit.invalid-retention-period',
            message: "The audit log's retention period [{$given}] isn't a whole number of days above 0.",
            why: 'Records are deleted once older than the period, so it must say how many whole days to keep them.',
            fix: 'Set larasell.audit.retention.days to a whole number of days above 0, or null to keep records forever.',
        );
    }
}
