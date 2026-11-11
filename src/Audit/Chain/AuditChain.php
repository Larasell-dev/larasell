<?php

namespace Larasell\Larasell\Audit\Chain;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use JsonException;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use stdClass;

/**
 * Chains the audit log's records, so a record changed or deleted outside the app shows. Each
 * record's hash is the SHA-256 of the previous record's hash (empty for the first) followed by its
 * columns as one JSON object: keys sorted at every level, no whitespace, slashes and Unicode
 * unescaped, seq and event_version as integers, properties as stored, and dates in UTC as
 * Y-m-d H:i:s.u.
 *
 * @internal The Auditor appends to it; larasell:verify-audit-log and the prune command use the rest.
 */
class AuditChain
{
    public const string TABLE = 'larasell_audit_log';

    /**
     * One row with the last record's seq and hash. Appending updates it first, so it holds the
     * lock that keeps writers in line until the transaction ends, on every database.
     */
    public const string HEAD = 'larasell_audit_log_head';

    /**
     * The columns encoded for a record's hash. Its previous_hash goes in front of them instead.
     */
    private const array COLUMNS = ['id', 'seq', 'event', 'event_version', 'category', 'actor_type', 'actor_id', 'unknown_actor_reason', 'subject_type', 'subject_id', 'properties', 'source', 'correlation_id', 'occurred_at', 'created_at'];

    private const int JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    /**
     * Stores the record as the next in the chain, waiting for any other writer to commit first.
     *
     * @param  array<string, mixed>  $record  Every column but seq, previous_hash and hash, as stored.
     */
    public function append(array $record): void
    {
        $event = (string) ($record['event'] ?? '');

        foreach (['seq', 'previous_hash', 'hash'] as $column) {
            if (array_key_exists($column, $record)) {
                throw AuditFailed::alreadyChained($event, $column);
            }
        }

        // Auditor rejects fractions before storing them; checked again on what is hashed, since
        // a float has no single way to be written, so the hash couldn't be reproduced.
        $fraction = $this->fraction(json_decode((string) ($record['properties'] ?? 'null'), true));

        if ($fraction !== null) {
            throw AuditFailed::invalidProperty($event, $fraction, 'float');
        }

        DB::transaction(function () use ($record, $event): void {
            // Created on the first append, rather than by a migration.
            DB::table(self::HEAD)->insertOrIgnore(['id' => 1, 'seq' => 0, 'hash' => null]);
            $before = (int) DB::table(self::HEAD)->where('id', 1)->value('seq');
            DB::table(self::HEAD)->where('id', 1)->increment('seq');
            $head = DB::table(self::HEAD)->where('id', 1)->sole();

            // The lock makes the increment ours alone, so the head moved by exactly one.
            if ((int) $head->seq !== $before + 1) {
                throw AuditFailed::headOutOfStep($event);
            }

            $record = [...$record, 'seq' => (int) $head->seq, 'previous_hash' => $head->hash];
            $record['hash'] = $this->hash($record);

            DB::table(self::TABLE)->insert($record);

            // Read back, so a column the database truncated or rewrote fails now, rolling back the
            // change, instead of showing up as a broken chain later.
            $stored = DB::table(self::TABLE)->where('id', $record['id'])->first();

            if ($stored === null || ! $this->readable($stored) || ! hash_equals($record['hash'], $this->hash($stored))) {
                throw AuditFailed::storedDifferently((string) $record['event']);
            }

            if (DB::table(self::HEAD)->where('id', 1)->update(['hash' => $record['hash']]) !== 1) {
                throw AuditFailed::headOutOfStep($event);
            }
        });
    }

    /**
     * The path of the first fraction in decoded properties, e.g. price.amount, or null without one.
     */
    private function fraction(mixed $value, string $path = ''): ?string
    {
        if (is_float($value)) {
            return $path;
        }

        foreach (is_array($value) ? $value : [] as $key => $item) {
            $found = $this->fraction($item, ltrim("{$path}.{$key}", '.'));

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The hash of a stored record, from its columns as the database returns them.
     *
     * @param  array<string, mixed>|object  $record
     */
    public function hash(array|object $record): string
    {
        return hash('sha256', (((array) $record)['previous_hash'] ?? '').$this->canonical($record));
    }

    /**
     * Checks every stored record against its hash and the record before it, from the start of the
     * chain, or where the newest prune record says it starts, to the head.
     */
    public function verify(): Verification
    {
        $start = $this->start();
        $previous = null;
        $count = 0;

        foreach (DB::table(self::TABLE)->orderBy('seq')->lazyById(1000, 'seq') as $record) {
            $seq = (int) $record->seq;
            $expectedSeq = $previous === null ? ($start['seq'] ?? 0) + 1 : (int) $previous->seq + 1;
            $expectedLink = $previous === null ? $start['sha256'] ?? null : $previous->hash;

            if ($seq !== $expectedSeq) {
                return Verification::broken($seq, 'missing_event', $count);
            }

            // Unreadable JSON or dates can't have been written by the Auditor, so they were changed.
            if (! $this->readable($record)) {
                return Verification::broken($seq, 'unreadable_record', $count);
            }

            if (! hash_equals($this->hash($record), (string) $record->hash)) {
                return Verification::broken($seq, 'hash_mismatch', $count);
            }

            if ($record->previous_hash !== $expectedLink) {
                return Verification::broken($seq, 'link_mismatch', $count);
            }

            $previous = $record;
            $count++;
        }

        $head = DB::table(self::HEAD)->where('id', 1)->first();
        $lastSeq = $previous === null ? $start['seq'] ?? 0 : (int) $previous->seq;
        $lastHash = $previous === null ? $start['sha256'] ?? null : $previous->hash;

        // No head row means nothing was ever appended.
        $headSeq = $head === null ? 0 : (int) $head->seq;
        $headHash = $head?->hash;

        if ($headSeq !== $lastSeq || $headHash !== $lastHash) {
            return Verification::broken($headSeq, 'head_mismatch', $count);
        }

        return Verification::passed($previous === null ? null : $lastSeq, $lastHash, $count);
    }

    /**
     * The last deleted record the chain starts after, for the prune record to carry: the record
     * before the oldest one stored, or the head when every record was deleted.
     *
     * @return array{seq: int, sha256: string}|null
     */
    public function startsAfter(): ?array
    {
        $first = DB::table(self::TABLE)->orderBy('seq')->first(['seq', 'previous_hash']);

        if ($first !== null) {
            return $first->previous_hash === null ? null : ['seq' => (int) $first->seq - 1, 'sha256' => $first->previous_hash];
        }

        return $this->head();
    }

    /**
     * Where the newest prune record says the chain starts, or null for its very first record.
     *
     * @return array{seq: int, sha256: string}|null
     */
    private function start(): ?array
    {
        $properties = DB::table(self::TABLE)->where('event', 'audit_log.pruned')->orderByDesc('seq')->value('properties');
        $startsAfter = is_string($properties) ? json_decode($properties, true)['starts_after'] ?? null : null;

        return is_array($startsAfter) && is_int($startsAfter['seq'] ?? null) && is_string($startsAfter['sha256'] ?? null) ? $startsAfter : null;
    }

    /**
     * A stored record's columns as the spec reads them, whatever form the database returned them
     * in, with its previous_hash and hash after them. Exports write records like this.
     *
     * @param  array<string, mixed>|object  $record
     * @return array<string, mixed>
     */
    public function normalize(array|object $record): array
    {
        $record = (array) $record;
        $fields = [];

        foreach (self::COLUMNS as $column) {
            $value = $record[$column] ?? null;

            $fields[$column] = match ($column) {
                'seq', 'event_version' => (int) $value,
                'properties' => json_decode((string) $value, false, flags: JSON_THROW_ON_ERROR),
                'occurred_at', 'created_at' => CarbonImmutable::parse((string) $value, 'UTC')->format('Y-m-d H:i:s.u'),
                default => $value === null ? null : (string) $value,
            };
        }

        return [...$fields, 'previous_hash' => $record['previous_hash'] ?? null, 'hash' => $record['hash'] ?? null];
    }

    /**
     * Whether the stored record's properties and dates can be read, so it can be hashed.
     */
    public function readable(object $record): bool
    {
        try {
            $this->normalize($record);

            return true;
        } catch (JsonException|InvalidFormatException) {
            return false;
        }
    }

    /**
     * The newest record's seq and hash, or null before the first.
     *
     * @return array{seq: int, sha256: string}|null
     */
    public function head(): ?array
    {
        $head = DB::table(self::HEAD)->where('id', 1)->first();

        return $head === null || $head->hash === null ? null : ['seq' => (int) $head->seq, 'sha256' => $head->hash];
    }

    /**
     * @param  array<string, mixed>|object  $record
     */
    private function canonical(array|object $record): string
    {
        return $this->encode(array_diff_key($this->normalize($record), ['previous_hash' => true, 'hash' => true]));
    }

    private function encode(mixed $value): string
    {
        if ($value instanceof stdClass || is_array($value) && ! array_is_list($value)) {
            $value = (array) $value;
            ksort($value, SORT_STRING);

            return '{'.implode(',', array_map(
                fn (string|int $key, mixed $item): string => json_encode((string) $key, self::JSON_FLAGS).':'.$this->encode($item),
                array_keys($value),
                $value,
            )).'}';
        }

        if (is_array($value)) {
            return '['.implode(',', array_map($this->encode(...), $value)).']';
        }

        return json_encode($value, self::JSON_FLAGS);
    }
}
