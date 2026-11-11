<?php

namespace Larasell\Larasell\Audit\Actions;

use Larasell\Larasell\Audit\RetentionPeriod;

/**
 * How to prune: the retention period, or null to keep records forever, and whether a legal hold
 * keeps every record.
 */
final readonly class PruneAuditLogPayload
{
    public function __construct(public ?RetentionPeriod $retention, public bool $legalHold) {}
}
