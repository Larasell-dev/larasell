<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Larasell\Larasell\Audit\Actors\Actor;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Actors\UnknownActor;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Enums\AuditSource;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Audit\Subject;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));

    // Jobs run where they're dispatched, whatever queue the environment configures.
    config(['queue.default' => 'sync']);
});

/**
 * A record as a module or extension defines one.
 *
 * @param  array<string, mixed>  $properties
 */
function bookArchived(array $properties = ['title' => 'Dune'], ?CarbonInterface $occurredAt = null): AuditRecord
{
    return new class($properties, $occurredAt) extends AuditRecord
    {
        /**
         * @param  array<string, mixed>  $properties
         */
        public function __construct(private readonly array $properties, private readonly ?CarbonInterface $at) {}

        public function event(): string
        {
            return 'book.archived';
        }

        public function version(): int
        {
            return 2;
        }

        public function category(): AuditCategory
        {
            return AuditCategory::BusinessCritical;
        }

        public function subject(): ?Subject
        {
            return new Subject('book', 42);
        }

        public function properties(): array
        {
            return $this->properties;
        }

        public function occurredAt(): CarbonInterface
        {
            return $this->at ?? parent::occurredAt();
        }
    };
}

/**
 * Records in a transaction, as every audited change does.
 */
function audit(AuditRecord $record, Origin $origin = new Origin(new SystemActor('inventory-reconciliation'), AuditSource::Cli)): void
{
    DB::transaction(fn () => app(Auditor::class)->record($record, $origin));
}

/**
 * A stored record's times as UTC with microseconds, however the database returns them; PostgreSQL
 * drops trailing zeros.
 *
 * @return array{occurred_at: string, created_at: string}
 */
function storedTimes(object $record): array
{
    return [
        'occurred_at' => Carbon::parse($record->occurred_at, 'UTC')->format('Y-m-d H:i:s.u'),
        'created_at' => Carbon::parse($record->created_at, 'UTC')->format('Y-m-d H:i:s.u'),
    ];
}

/**
 * Someone who acts, as an app's user model would be.
 */
function librarian(string $id = '7'): Actor
{
    return new readonly class($id) implements Actor
    {
        public function __construct(private string $id) {}

        public function auditActorType(): string
        {
            return 'librarian';
        }

        public function auditActorId(): string
        {
            return $this->id;
        }
    };
}

it('stores a record with its event, origin, subject and properties', function () {
    // Act
    audit(bookArchived(), Origin::cli(new SystemActor('inventory-reconciliation')));

    // Assert
    $record = DB::table('larasell_audit_log')->sole();

    expect(Str::isUlid($record->id))->toBeTrue()
        ->and($record)->toMatchArray([
            'event' => 'book.archived',
            'event_version' => 2,
            'category' => 'business_critical',
            'actor_type' => 'system',
            'actor_id' => 'inventory-reconciliation',
            'unknown_actor_reason' => null,
            'subject_type' => 'book',
            'subject_id' => '42',
            'source' => 'cli',
        ])
        ->and(storedTimes($record))->toBe(['occurred_at' => '2026-10-30 12:00:00.000000', 'created_at' => '2026-10-30 12:00:00.000000'])
        ->and(json_decode($record->properties, true))->toBe(['title' => 'Dune'])
        ->and($record->correlation_id)->toBeString()->not->toBeEmpty();
});

it('stores the source the origin names, whatever runs the code', function (Closure $origin, string $source) {
    // Act
    audit(bookArchived(), $origin());

    // Assert
    expect(DB::table('larasell_audit_log')->sole())->toMatchArray([
        'actor_type' => 'librarian',
        'actor_id' => '7',
        'source' => $source,
    ]);
})->with([
    'web' => [fn () => Origin::web(librarian()), 'web'],
    'api' => [fn () => Origin::api(librarian()), 'api'],
    'cli' => [fn () => Origin::cli(librarian()), 'cli'],
    'a job on its own' => [fn () => Origin::job(librarian()), 'job'],
    'a job dispatched from a request' => [fn () => Origin::web(librarian())->inJob(), 'job'],
]);

it('keeps the actor of whoever dispatched a job', function () {
    // Arrange
    $origin = Origin::api(librarian('9'));

    // Act
    $inJob = $origin->inJob();

    // Assert
    expect($inJob->actor)->toBe($origin->actor)
        ->and($inJob->source)->toBe(AuditSource::Job)
        ->and($origin->source)->toBe(AuditSource::Api);
});

it('has no updated_at, since records never change', function () {
    // Act & Assert
    expect(Schema::getColumnListing('larasell_audit_log'))->toContain('created_at')->not->toContain('updated_at');
});

it('stores times in UTC, and when it happened apart from when it was recorded', function () {
    // Arrange
    config(['app.timezone' => 'Europe/Berlin']);
    date_default_timezone_set('Europe/Berlin');

    try {
        // 14:00 in Berlin is 13:00 UTC.
        $this->travelTo(Carbon::parse('2026-10-30 14:00:00'));

        // Act
        audit(bookArchived(occurredAt: now()->subMinutes(5)));

        // Assert
        expect(storedTimes(DB::table('larasell_audit_log')->sole()))->toBe([
            'occurred_at' => '2026-10-30 12:55:00.000000',
            'created_at' => '2026-10-30 13:00:00.000000',
        ]);
    } finally {
        date_default_timezone_set('UTC');
    }
});

it('refuses to record outside a transaction, where the change could commit without its record', function () {
    // Act & Assert
    expect(fn () => outsideTestTransaction(fn () => app(Auditor::class)->record(bookArchived(), Origin::cli(new SystemActor('inventory-reconciliation')))))
        ->toThrow(AuditFailed::class);

    expect(DB::table('larasell_audit_log')->count())->toBe(0);
});

it('records outside a transaction when the record changes nothing it must commit with', function () {
    // Arrange
    $signInFailed = new class extends AuditRecord
    {
        public function event(): string
        {
            return 'user.sign_in_failed';
        }

        public function category(): AuditCategory
        {
            return AuditCategory::Security;
        }

        public function subject(): ?Subject
        {
            return null;
        }

        public function properties(): array
        {
            return [];
        }

        public function requiresTransaction(): bool
        {
            return false;
        }
    };

    // Act
    app(Auditor::class)->record($signInFailed, Origin::web(new UnknownActor('No one was signed in.')));

    // Assert
    expect(DB::table('larasell_audit_log')->sole())->toMatchArray(['event' => 'user.sign_in_failed', 'source' => 'web']);
});

it('stores an unknown actor with the reason it is unknown', function () {
    // Act
    audit(bookArchived(), Origin::cli(new UnknownActor('Imported from the previous shop system.')));

    // Assert
    expect(DB::table('larasell_audit_log')->sole())->toMatchArray([
        'actor_type' => 'unknown',
        'actor_id' => null,
        'unknown_actor_reason' => 'Imported from the previous shop system.',
    ]);
});

it('treats an unknown actor as an actor without an id', function () {
    // Arrange
    $unknown = new UnknownActor('Imported.');

    // Act & Assert
    expect($unknown)->toBeInstanceOf(Actor::class)
        ->and($unknown->auditActorType())->toBe('unknown')
        ->and($unknown->auditActorId())->toBeNull();
});

it('requires a name for system actors and a reason for unknown ones', function (Closure $actor) {
    // Act & Assert
    expect($actor)->toThrow(AuditFailed::class);
})->with([
    'a system actor without a name' => [fn () => new SystemActor('')],
    'an unknown actor without a reason' => [fn () => new UnknownActor('  ')],
]);

it('shares the correlation ID of a request with the jobs it dispatches', function () {
    // Arrange
    $origin = Origin::web(new SystemActor('librarian'));

    // Act
    audit(bookArchived(), $origin);
    dispatch(static function () use ($origin): void {
        audit(bookArchived(), $origin->inJob());
    });
    audit(bookArchived(), $origin);

    // Assert
    $records = DB::table('larasell_audit_log')->orderBy('seq')->get();

    expect($records->pluck('source')->all())->toBe(['web', 'job', 'web'])
        ->and($records->pluck('actor_id')->unique()->all())->toBe(['librarian'])
        ->and($records->pluck('correlation_id')->unique())->toHaveCount(1);
});

it('rejects properties that look like credentials', function (array $properties) {
    // Act & Assert
    expect(fn () => audit(bookArchived($properties)))->toThrow(AuditFailed::class);

    expect(DB::table('larasell_audit_log')->count())->toBe(0);
})->with([
    'a password' => [['password' => 'secret123']],
    'a token hash' => [['token_hash' => 'abc']],
    'a camel-cased token' => [['apiToken' => 'larasell_abc']],
    'a client secret' => [['client_secret' => 'abc']],
    'nested credentials' => [['integration' => ['name' => 'ERP', 'credentials' => ['user' => 'erp']]]],
]);

it('rejects properties that aren\'t plain values, so each record decides how to store them', function (array $properties) {
    // Act & Assert
    expect(fn () => audit(bookArchived($properties)))->toThrow(AuditFailed::class);
})->with([
    'an object' => [fn () => ['book' => new stdClass]],
    'a date' => [fn () => ['archived_at' => now()]],
    'a nested object' => [fn () => ['book' => ['archived_at' => now()]]],
    'a fraction' => [fn () => ['price' => 9.99]],
]);
