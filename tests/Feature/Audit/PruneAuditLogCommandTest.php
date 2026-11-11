<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\AuditRecords\AuditLogPruned;
use Larasell\Larasell\Audit\Chain\AuditChain;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Events\AuditLogPruned as AuditLogPrunedEvent;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Facades\Audit;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Audit\Subject;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-30 12:00:00'));
});

/**
 * Records an action that happened the given time ago, and returns its place in the chain.
 */
function storedRecord(int $daysAgo, int $andSeconds = 0): int
{
    $at = now()->subDays($daysAgo)->subSeconds($andSeconds);

    DB::transaction(fn () => app(Auditor::class)->record(new class($at) extends AuditRecord
    {
        public function __construct(private readonly CarbonInterface $at) {}

        public function event(): string
        {
            return 'book.archived';
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

        public function occurredAt(): CarbonInterface
        {
            return $this->at;
        }
    }, Origin::cli(new SystemActor('tests'))));

    return (int) DB::table('larasell_audit_log')->max('seq');
}

/**
 * @return list<int>
 */
function remainingRecords(): array
{
    return DB::table('larasell_audit_log')->where('event', 'book.archived')->orderBy('seq')->pluck('seq')->map(fn ($seq) => (int) $seq)->all();
}

/**
 * The newest prune record's properties.
 *
 * @return array<string, mixed>
 */
function pruneProperties(): array
{
    $properties = json_decode(DB::table('larasell_audit_log')->where('event', 'audit_log.pruned')->orderByDesc('seq')->value('properties'), true);

    // In the order the record writes them; MySQL's JSON column reorders keys.
    return array_replace(array_intersect_key(array_flip(['legal_hold', 'retention_days', 'deleted', 'starts_after']), $properties), $properties);
}

it('keeps every record by default', function () {
    // Arrange
    $old = storedRecord(5000);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    expect(remainingRecords())->toBe([$old])
        ->and(pruneProperties())->toBe(['legal_hold' => false, 'retention_days' => null, 'deleted' => 0, 'starts_after' => null]);
});

it('deletes the oldest records older than the retention period, and the chain still verifies', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30]);

    storedRecord(400);
    storedRecord(31);
    $kept = storedRecord(29);
    $deletedHash = DB::table('larasell_audit_log')->where('seq', 2)->value('hash');

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    expect(remainingRecords())->toBe([$kept])
        ->and(pruneProperties())->toBe(['legal_hold' => false, 'retention_days' => 30, 'deleted' => 2, 'starts_after' => ['seq' => 2, 'sha256' => $deletedHash]])
        ->and(app(AuditChain::class)->verify()->passed)->toBeTrue();
});

it('only deletes from the start, keeping an old record behind a newer one', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30]);

    storedRecord(400);
    $newer = storedRecord(1);
    $backdated = storedRecord(400);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    expect(remainingRecords())->toBe([$newer, $backdated])
        ->and(app(AuditChain::class)->verify()->passed)->toBeTrue();
});

it('keeps a record until it is older than the retention period, to the second', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30]);

    storedRecord(30, andSeconds: 1);
    $exactly = storedRecord(30);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    expect(remainingRecords())->toBe([$exactly]);
});

it('keeps where the chain starts when a later run deletes nothing, or everything', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30]);

    storedRecord(400);
    storedRecord(10);
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();
    $anchor = pruneProperties()['starts_after'];

    // Act & Assert
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    expect(pruneProperties()['starts_after'])->toBe($anchor)
        ->and(app(AuditChain::class)->verify()->passed)->toBeTrue();

    $this->travel(400)->days();
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    expect(DB::table('larasell_audit_log')->count())->toBe(1)
        ->and(pruneProperties()['starts_after']['seq'])->toBe(4)
        ->and(app(AuditChain::class)->verify()->passed)->toBeTrue();
});

it('reads a period set as a string, as env() returns it', function () {
    // Arrange
    config(['larasell.audit.retention.days' => '30']);

    storedRecord(31);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    expect(remainingRecords())->toBe([])
        ->and(pruneProperties()['retention_days'])->toBe(30);
});

it('deletes nothing under a legal hold, and records that it was held', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30, 'larasell.audit.legal_hold' => true]);

    $old = storedRecord(400);

    // Act
    $this->artisan('larasell:prune-audit-log')
        ->expectsOutputToContain('legal hold')
        ->assertSuccessful();

    // Assert
    expect(remainingRecords())->toBe([$old])
        ->and(pruneProperties())->toMatchArray(['legal_hold' => true, 'deleted' => 0]);
});

it('records each run as the command', function () {
    // Arrange
    Audit::fake([AuditLogPruned::class]);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    Audit::assertRecorded(AuditLogPruned::class, fn (AuditLogPruned $record, Origin $origin) => $origin == Origin::cli(new SystemActor('larasell:prune-audit-log'))
        && $record->subject() === null);
});

it('tells the app once the log is pruned, so it can clean up its own copies', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30]);
    Event::fake([AuditLogPrunedEvent::class]);

    storedRecord(400);
    storedRecord(1);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertSuccessful();

    // Assert
    Event::assertDispatched(AuditLogPrunedEvent::class, fn (AuditLogPrunedEvent $event) => $event->deleted === 1);
});

it('refuses an invalid retention period without deleting anything', function (mixed $days) {
    // Arrange
    config(['larasell.audit.retention.days' => $days]);

    $old = storedRecord(400);

    // Act
    $this->artisan('larasell:prune-audit-log')->assertFailed();

    // Assert
    expect(remainingRecords())->toBe([$old])
        ->and(DB::table('larasell_audit_log')->where('event', 'audit_log.pruned')->exists())->toBeFalse();
})->with([
    'zero days' => [0],
    'negative days' => [-30],
    'not a number' => ['a year'],
    'a fraction' => [30.5],
]);

it('refuses to prune over a gap, so pruning can\'t hide a record deleted outside the app', function () {
    // Arrange
    config(['larasell.audit.retention.days' => 30]);

    storedRecord(400);
    storedRecord(300);
    storedRecord(31);
    storedRecord(29);
    DB::table('larasell_audit_log')->where('seq', 2)->delete();

    // Act & Assert
    expect(fn () => $this->artisan('larasell:prune-audit-log')->run())->toThrow(AuditFailed::class);

    expect(remainingRecords())->toBe([1, 3, 4])
        ->and(app(AuditChain::class)->verify()->reason)->toBe('missing_event');
});
