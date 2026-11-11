<?php

namespace Larasell\Larasell\Audit\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\RetentionPeriod;
use Larasell\Larasell\Audit\Subject;

/**
 * The retention period was applied: how long records are kept, whether a legal hold was in place,
 * how many records were deleted, and the deleted record the chain now starts after, so the log
 * can still be verified.
 *
 * @internal Recorded by PruneAuditLog. Its event name, audit_log.pruned, is stable.
 */
class AuditLogPruned extends AuditRecord
{
    /**
     * @param  array{seq: int, sha256: string}|null  $startsAfter
     */
    public function __construct(
        private readonly bool $legalHold,
        private readonly ?RetentionPeriod $retention,
        private readonly int $deleted,
        private readonly ?array $startsAfter,
    ) {}

    public function event(): string
    {
        return 'audit_log.pruned';
    }

    /**
     * Version 1 had a retention period and deleted count per category, and no starts_after.
     */
    public function version(): int
    {
        return 2;
    }

    public function category(): AuditCategory
    {
        return AuditCategory::AuditAccess;
    }

    public function subject(): ?Subject
    {
        return null;
    }

    public function properties(): array
    {
        return ['legal_hold' => $this->legalHold, 'retention_days' => $this->retention?->days, 'deleted' => $this->deleted, 'starts_after' => $this->startsAfter];
    }
}
