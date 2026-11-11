<?php

namespace Larasell\Larasell\Audit\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Larasell\Chronicle\Exceptions\StructuredLogicException;

/**
 * A stored audit record, read for the audit log page. Records are written by the Auditor and
 * never changed or deleted through the app, so this model refuses to.
 *
 * @internal Record with Auditor::record(); this model and its columns may change.
 *
 * @property string $id
 * @property string $event
 * @property int $event_version
 * @property string $category
 * @property string $actor_type
 * @property ?string $actor_id
 * @property ?string $unknown_actor_reason
 * @property ?string $subject_type
 * @property ?string $subject_id
 * @property ?array<string, mixed> $properties Null when the stored JSON is unreadable.
 * @property string $source
 * @property ?string $correlation_id
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface $created_at
 * @property int $seq
 * @property ?string $previous_hash
 * @property string $hash
 */
class AuditLogEntry extends Model
{
    use HasUlids;

    protected $table = 'larasell_audit_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'event_version' => 'integer',
            'seq' => 'integer',
            'properties' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * The records matching the audit log page's filters. Days are in the app's time zone.
     *
     * @param  Builder<self>  $query
     * @param  array{event?: string, actor_type?: string, actor_id?: string, subject_type?: string, subject_id?: string, from?: string, until?: string}  $filters  Validated by AuditLogFiltersRequest.
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters): void
    {
        $timezone = config('app.timezone');
        $day = fn (string $date): CarbonImmutable => CarbonImmutable::parse($date, $timezone)->startOfDay();
        $utc = fn (CarbonImmutable $time): string => $time->utc()->format('Y-m-d H:i:s.u');

        $query
            ->when($filters['event'] ?? null, fn (Builder $query, string $event) => $query->where('event', $event))
            ->when($filters['actor_type'] ?? null, fn (Builder $query, string $type) => $query->where('actor_type', $type))
            ->when($filters['actor_id'] ?? null, fn (Builder $query, string $id) => $query->where('actor_id', $id))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, string $id) => $query->where('subject_id', $id))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('occurred_at', '>=', $utc($day($from))))
            ->when($filters['until'] ?? null, fn (Builder $query, string $until) => $query->where('occurred_at', '<', $utc($day($until)->addDay())));
    }

    protected static function booted(): void
    {
        $refuse = fn () => throw new StructuredLogicException(
            errorCode: 'larasell.audit.append-only',
            message: 'Audit records are append-only: record new ones with Auditor::record().',
            why: 'The audit log is evidence of what happened, so a stored record is never changed or deleted.',
            fix: 'Record a new audit record with Auditor::record() instead.',
        );

        static::saving($refuse);
        static::deleting($refuse);
    }
}
