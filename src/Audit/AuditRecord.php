<?php

namespace Larasell\Larasell\Audit;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Larasell\Larasell\Audit\Enums\AuditCategory;

/**
 * One kind of audited action, e.g. an API token was created. Pass an instance to
 * Auditor::record() in the transaction of the change it describes.
 *
 * Concrete records live in an AuditRecords namespace, grouped by what they're about,
 * e.g. AuditRecords\ApiTokens\ApiTokenCreated.
 *
 * An abstract class rather than an interface, so new methods can come with a default.
 *
 * @public Extend it for each audited action.
 */
abstract class AuditRecord
{
    /**
     * The stable name records are stored and searched by, e.g. api_token.created. Never rename it.
     */
    abstract public function event(): string;

    abstract public function category(): AuditCategory;

    /**
     * What the action was done to, or null if to nothing in particular.
     */
    abstract public function subject(): ?Subject;

    /**
     * Only what explains the action, as plain values: no credentials, and dates formatted by the
     * record. Auditor rejects anything else.
     *
     * @return array<string, mixed>
     */
    abstract public function properties(): array;

    /**
     * Bump it when properties() changes shape, so older records can still be read as they were stored.
     */
    public function version(): int
    {
        return 1;
    }

    /**
     * Whether the record describes a change it must commit or roll back with, so Auditor::record()
     * refuses to store it outside a transaction. Records of actions that change nothing, such as a
     * failed sign-in, return false.
     */
    public function requiresTransaction(): bool
    {
        return true;
    }

    /**
     * When the action happened, by default when it is recorded.
     */
    public function occurredAt(): CarbonInterface
    {
        return Date::now();
    }
}
