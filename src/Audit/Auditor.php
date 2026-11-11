<?php

namespace Larasell\Larasell\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Larasell\Larasell\Audit\Actors\UnknownActor;
use Larasell\Larasell\Audit\Chain\AuditChain;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;

/**
 * Writes audit records to the shop's database, the audit log's source of truth. Records can only
 * be added, never changed through the app, and each is chained to the one before it, so changing
 * or deleting one outside the app shows when the log is verified.
 *
 * Record in the transaction of the change, so both commit or neither does, with the Origin the
 * app passed along: who made the change and how it came in. Records of actions that change
 * nothing, such as a failed sign-in, opt out with requiresTransaction().
 *
 *     DB::transaction(function () use ($auditor, $origin) {
 *         // the change
 *         $auditor->record(new ProductArchived($product), $origin);
 *     });
 *
 * @public Inject it, or use the Audit facade, and call record(); its other methods are internal.
 * Tests fake it with Audit::fake().
 */
class Auditor
{
    /**
     * Property names made of any of these words are rejected, e.g. password, token_hash or apiToken.
     */
    private const array CREDENTIAL_WORDS = ['password', 'passwords', 'secret', 'secrets', 'token', 'tokens', 'hash', 'credential', 'credentials'];

    /**
     * The Laravel context key, so logs of the same request or job carry the ID too, and jobs
     * dispatched from a request inherit it.
     */
    private const string CORRELATION_ID = 'correlation_id';

    /**
     * What the audit log calls actors, by actor type.
     *
     * @var array<string, array{label: string, model: class-string<Model>}>
     */
    protected array $actorTypes = [];

    public function __construct(protected readonly AuditChain $chain) {}

    /**
     * Stores the record, failing rather than losing it, so the change it describes rolls back too.
     *
     * @public
     *
     * @throws AuditFailed
     */
    public function record(AuditRecord $record, Origin $origin): void
    {
        $event = $record->event();

        if ($record->requiresTransaction() && DB::transactionLevel() === 0) {
            throw AuditFailed::outsideTransaction($event);
        }

        $properties = $record->properties();
        $this->validate($event, $properties);
        $subject = $record->subject();
        $actor = $origin->actor;

        $this->store($record, $origin, [
            'id' => (string) Str::ulid(),
            'event' => $event,
            'event_version' => $record->version(),
            'category' => $record->category()->value,
            'actor_type' => $actor->auditActorType(),
            'actor_id' => $actor->auditActorId(),
            'unknown_actor_reason' => $actor instanceof UnknownActor ? $actor->reason : null,
            'subject_type' => $subject?->type,
            'subject_id' => $subject?->id,
            // An object even when empty, so every record's properties have the same JSON type.
            'properties' => $properties === [] ? '{}' : json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'source' => $origin->source->value,
            'correlation_id' => $this->correlationId(),
            // copy(), since the record may return a model's own date.
            'occurred_at' => $record->occurredAt()->copy()->utc()->format('Y-m-d H:i:s.u'),
            'created_at' => Date::now()->utc()->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @internal Called by the apps and modules whose models act, so the audit log can name their
     * actors, e.g. "API token: Deploy", from the model's name attribute.
     *
     * @param  class-string<Model>  $model
     */
    public function actorType(string $type, string $label, string $model): void
    {
        $this->actorTypes[$type] = ['label' => $label, 'model' => $model];
    }

    /**
     * @internal
     *
     * @return array{label: string, model: class-string<Model>}|null
     */
    public function findActorType(string $type): ?array
    {
        return $this->actorTypes[$type] ?? null;
    }

    /**
     * Appends the checked record to the chain. AuditFake keeps it in memory instead.
     *
     * @param  array<string, mixed>  $row
     */
    protected function store(AuditRecord $record, Origin $origin, array $row): void
    {
        $this->chain->append($row);
    }

    /**
     * @param  array<array-key, mixed>  $properties
     */
    private function validate(string $event, array $properties, string $path = ''): void
    {
        foreach ($properties as $key => $value) {
            $keyPath = ltrim($path.'.'.$key, '.');

            if (is_string($key) && array_intersect(explode('_', Str::snake($key)), self::CREDENTIAL_WORDS) !== []) {
                throw AuditFailed::credentialProperty($event, $keyPath);
            }

            if (is_array($value)) {
                $this->validate($event, $value, $keyPath);
            } elseif (is_float($value) || ! is_scalar($value) && $value !== null) {
                throw AuditFailed::invalidProperty($event, $keyPath, get_debug_type($value));
            }
        }
    }

    private function correlationId(): string
    {
        $id = Context::get(self::CORRELATION_ID);

        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid();
            Context::add(self::CORRELATION_ID, $id);
        }

        return $id;
    }
}
