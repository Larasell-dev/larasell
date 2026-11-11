<?php

namespace Larasell\Larasell\Audit\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;

/**
 * Why Auditor::record() refused or failed to store a record.
 *
 * @public Catch it; only the audit log creates it, so its named constructors are internal.
 */
class AuditFailed extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    /**
     * @internal Thrown by AuditFake::failing(), so tests can check a change rolls back.
     */
    public static function faked(string $event): static
    {
        return static::structured(
            errorCode: 'larasell.audit.faked-failure',
            message: "Audit record [{$event}] failed, as the test told Audit::fake() to.",
            why: 'The test called Audit::fake()->failing() to check what happens when a record can\'t be stored.',
            fix: 'Expect the failure in the test, or leave out failing().',
        );
    }

    /**
     * @internal
     */
    public static function outsideTransaction(string $event): static
    {
        return static::structured(
            errorCode: 'larasell.audit.outside-transaction',
            message: "Audit record [{$event}] was recorded outside a database transaction.",
            why: 'Outside a transaction, the change could be stored without its audit record, or the record without its change.',
            fix: 'Make the change and record it inside the same DB::transaction().',
        );
    }

    /**
     * @internal
     */
    public static function credentialProperty(string $event, string $property): static
    {
        return static::structured(
            errorCode: 'larasell.audit.credential-property',
            message: "Audit record [{$event}] has property [{$property}], which looks like a credential.",
            why: 'Audit records are kept for a long time and read by other people, so they never store passwords, tokens, secrets or their hashes.',
            fix: "Leave [{$property}] out of properties(). The record's subject already says which credential it was about.",
        );
    }

    /**
     * @internal
     */
    public static function invalidProperty(string $event, string $property, string $type): static
    {
        return static::structured(
            errorCode: 'larasell.audit.invalid-property',
            message: "Audit record [{$event}] has property [{$property}] of type [{$type}].",
            why: 'Properties are stored as JSON and hashed into the audit log\'s chain. An object would be stored however it happens to serialize, possibly with more data than intended, and a fraction has no single way to be written.',
            fix: "Return a string, integer, boolean, null or array for [{$property}], e.g. a date formatted in UTC, or an amount in cents or as a string.",
        );
    }

    /**
     * @internal
     */
    public static function storedDifferently(string $event): static
    {
        return static::structured(
            errorCode: 'larasell.audit.stored-differently',
            message: "Audit record [{$event}] was stored differently from how it was hashed.",
            why: 'The database truncated or rewrote a column, e.g. a string longer than the column, so the record would break the chain.',
            fix: 'Check the larasell_audit_log columns and the database mode, e.g. MySQL strict mode, then try again.',
        );
    }

    /**
     * @internal
     */
    public static function alreadyChained(string $event, string $column): static
    {
        return static::structured(
            errorCode: 'larasell.audit.already-chained',
            message: "Audit record [{$event}] was appended with its own [{$column}].",
            why: 'The chain gives each record its seq, previous_hash and hash while appending, so one passed in would be overwritten.',
            fix: "Leave [{$column}] out of the record passed to AuditChain::append().",
        );
    }

    /**
     * @internal
     */
    public static function prunedOverGap(int $from, int $until, int $stored): static
    {
        $expected = $until - $from + 1;

        return static::structured(
            errorCode: 'larasell.audit.pruned-over-gap',
            message: "Pruning would delete records {$from} to {$until}, {$expected} records, but only {$stored} are stored.",
            why: 'A record in the oldest stretch was already deleted outside the app. Pruning over it would hide that it is missing.',
            fix: 'Run php artisan larasell:verify-audit-log, and find out who deleted the record before pruning again.',
        );
    }

    /**
     * @internal
     */
    public static function headOutOfStep(string $event): static
    {
        return static::structured(
            errorCode: 'larasell.audit.head-out-of-step',
            message: "Audit record [{$event}] couldn't move the chain's head along with it.",
            why: 'The larasell_audit_log_head table has no single head row, or another writer changed it without its lock.',
            fix: 'Check that larasell_audit_log_head holds exactly one row with id 1, and that nothing but AuditChain writes to it.',
        );
    }

    /**
     * @internal
     *
     * @param  class-string  $actor
     */
    public static function unnamedActor(string $actor, string $field): static
    {
        return static::structured(
            errorCode: 'larasell.audit.unnamed-actor',
            message: "{$actor} was created without a {$field}.",
            why: "Without a {$field}, the audit log can't tell who or what made the change.",
            fix: "Pass a {$field} that explains it to someone reading the audit log later.",
        );
    }
}
