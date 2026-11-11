<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Enums\AuditSource;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Facades\Audit;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Audit\Subject;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @param  array<string, mixed>  $properties
 */
function lent(string $event = 'book.lent', array $properties = ['title' => 'Dune']): AuditRecord
{
    return new class($event, $properties) extends AuditRecord
    {
        /** @param array<string, mixed> $properties */
        public function __construct(private readonly string $name, private readonly array $properties) {}

        public function event(): string
        {
            return $this->name;
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
    };
}

function lend(?AuditRecord $record = null, ?Origin $origin = null): void
{
    DB::transaction(fn () => Audit::record($record ?? lent(), $origin ?? Origin::cli(new SystemActor('library'))));
}

it('records through the facade to the audit log when not faked', function () {
    // Act
    lend();

    // Assert
    expect(Audit::getFacadeRoot())->toBe(app(Auditor::class))
        ->and(DB::table('larasell_audit_log')->where('event', 'book.lent')->count())->toBe(1);
});

it('keeps faked records out of the audit log, and asserts them by class', function () {
    // Arrange
    Audit::fake();
    $record = lent();

    // Act
    lend($record);

    // Assert
    expect(DB::table('larasell_audit_log')->count())->toBe(0);
    Audit::assertRecorded($record::class);
    Audit::assertRecordedTimes($record::class, 1);
    Audit::assertNotRecorded(SystemActor::class);
});

it('passes the record and its origin to the callback', function () {
    // Arrange
    Audit::fake();
    $origin = Origin::web(new SystemActor('library'));
    $record = lent();

    // Act
    lend($record, $origin);

    // Assert
    Audit::assertRecorded($record::class, fn (AuditRecord $recorded, Origin $recordedFrom) => $recorded === $record
        && $recordedFrom === $origin
        && $recordedFrom->source === AuditSource::Web);
});

it('fails the assertions when the records don\'t match', function () {
    // Arrange
    Audit::fake();
    $record = lent();
    lend($record);

    // Act & Assert
    expect(fn () => Audit::assertRecorded($record::class, fn () => false))->toThrow(AssertionFailedError::class)
        ->and(fn () => Audit::assertRecordedTimes($record::class, 2))->toThrow(AssertionFailedError::class)
        ->and(fn () => Audit::assertNotRecorded($record::class))->toThrow(AssertionFailedError::class)
        ->and(fn () => Audit::assertNothingRecorded())->toThrow(AssertionFailedError::class);
});

it('asserts nothing was recorded', function () {
    // Arrange
    Audit::fake();

    // Act & Assert
    Audit::assertNothingRecorded();
});

it('still refuses records the real auditor refuses', function () {
    // Arrange
    Audit::fake();
    $record = lent(properties: ['password' => 'Correct-Horse-9']);

    // Act & Assert
    expect(fn () => outsideTestTransaction(fn () => Audit::record(lent(), Origin::cli(new SystemActor('library')))))->toThrow(AuditFailed::class)
        ->and(fn () => lend($record))->toThrow(AuditFailed::class);

    Audit::assertNothingRecorded();
});

it('fails recording when told to, so the change rolls back', function () {
    // Arrange
    Audit::fake()->failing();

    // Act & Assert
    expect(fn () => lend())->toThrow(AuditFailed::class);

    Audit::assertNothingRecorded();
});

it('fakes only the given records, and stores the others', function () {
    // Arrange
    $faked = lent('book.lent');
    $stored = new class extends AuditRecord
    {
        public function event(): string
        {
            return 'book.returned';
        }

        public function category(): AuditCategory
        {
            return AuditCategory::BusinessCritical;
        }

        public function subject(): ?Subject
        {
            return null;
        }

        public function properties(): array
        {
            return [];
        }
    };
    Audit::fake([$faked::class]);

    // Act
    lend($faked);
    lend($stored);

    // Assert
    Audit::assertRecorded($faked::class);
    expect(DB::table('larasell_audit_log')->pluck('event')->all())->toBe(['book.returned']);
});
