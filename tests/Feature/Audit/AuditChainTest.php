<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Audit\Actors\SystemActor;
use Larasell\Larasell\Audit\Actors\UnknownActor;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Chain\AuditChain;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Audit\Subject;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-30 12:00:00.250000'));
});

/**
 * @param  array<string, mixed>  $properties
 */
function shelved(array $properties = ['title' => 'Dune'], bool $requiresTransaction = true): AuditRecord
{
    return new class($properties, $requiresTransaction) extends AuditRecord
    {
        /**
         * @param  array<string, mixed>  $properties
         */
        public function __construct(private readonly array $properties, private readonly bool $transaction) {}

        public function event(): string
        {
            return 'book.shelved';
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

        public function requiresTransaction(): bool
        {
            return $this->transaction;
        }
    };
}

function shelve(int $times = 1): void
{
    for ($i = 0; $i < $times; $i++) {
        DB::transaction(fn () => app(Auditor::class)->record(shelved(['title' => 'Dune', 'copy' => $i]), Origin::cli(new SystemActor('librarian'))));
    }
}

/**
 * @return array<int, object>
 */
function chainRows(): array
{
    return DB::table('larasell_audit_log')->orderBy('seq')->get()->keyBy('seq')->all();
}

/**
 * @return array{passed: bool, seq: ?int, reason: ?string}
 */
function verifyChain(): array
{
    $verification = app(AuditChain::class)->verify();

    return ['passed' => $verification->passed, 'seq' => $verification->seq, 'reason' => $verification->reason];
}

it('hashes each record with the one before it, as the canonical form in the spec says', function () {
    // Act
    DB::transaction(fn () => app(Auditor::class)->record(shelved(['title' => 'Dune', 'author' => ['last' => 'Herbert', 'first' => 'Frank'], 'shelf' => 3, 'tags' => ['b', 'a'], 'note' => "Ünïcode / \"quoted\"\n", 'lent' => null, 'new' => true]), Origin::cli(new SystemActor('librarian'))));
    DB::transaction(fn () => app(Auditor::class)->record(shelved([]), Origin::cli(new UnknownActor('Imported.'))));

    // Assert
    $rows = chainRows();

    // Written out by hand rather than built by the code under test.
    $first = '{"actor_id":"librarian","actor_type":"system","category":"business_critical","correlation_id":"'.$rows[1]->correlation_id.'","created_at":"2026-10-30 12:00:00.250000","event":"book.shelved","event_version":1,"id":"'.$rows[1]->id.'","occurred_at":"2026-10-30 12:00:00.250000","properties":{"author":{"first":"Frank","last":"Herbert"},"lent":null,"new":true,"note":"Ünïcode / \"quoted\"\n","shelf":3,"tags":["b","a"],"title":"Dune"},"seq":1,"source":"cli","subject_id":"42","subject_type":"book","unknown_actor_reason":null}';
    $second = '{"actor_id":null,"actor_type":"unknown","category":"business_critical","correlation_id":"'.$rows[2]->correlation_id.'","created_at":"2026-10-30 12:00:00.250000","event":"book.shelved","event_version":1,"id":"'.$rows[2]->id.'","occurred_at":"2026-10-30 12:00:00.250000","properties":{},"seq":2,"source":"cli","subject_id":"42","subject_type":"book","unknown_actor_reason":"Imported."}';

    expect($rows[1]->previous_hash)->toBeNull()
        ->and($rows[1]->hash)->toBe(hash('sha256', $first))
        ->and($rows[2]->previous_hash)->toBe($rows[1]->hash)
        ->and($rows[2]->hash)->toBe(hash('sha256', $rows[1]->hash.$second))
        ->and(verifyChain())->toBe(['passed' => true, 'seq' => 2, 'reason' => null]);
});

it('chains records made outside a transaction too', function () {
    // Arrange
    shelve();

    // Act
    app(Auditor::class)->record(shelved(requiresTransaction: false), Origin::cli(new UnknownActor('No one was signed in.')));

    // Assert
    expect(array_keys(chainRows()))->toBe([1, 2])
        ->and(verifyChain()['passed'])->toBeTrue();
});

it('verifies records however the database hands them back', function () {
    // Arrange
    shelve(2);

    // Act
    // As MySQL's JSON column reorders keys, and Postgres drops trailing zeros of a time.
    DB::table('larasell_audit_log')->where('seq', 1)->update([
        'properties' => '{"title": "Dune", "copy": 0}',
        'created_at' => '2026-10-30 12:00:00.25',
    ]);

    // Assert
    expect(verifyChain())->toBe(['passed' => true, 'seq' => 2, 'reason' => null]);
});

it('rejects fractional numbers, which the canonical form has no single spelling for', function () {
    // Act & Assert
    expect(fn () => DB::transaction(fn () => app(Auditor::class)->record(shelved(['price' => 9.99]), Origin::cli(new SystemActor('librarian')))))
        ->toThrow(AuditFailed::class);
});

it('finds a record changed in place', function () {
    // Arrange
    shelve(3);

    // Act
    DB::table('larasell_audit_log')->where('seq', 2)->update(['actor_id' => 'someone-else']);

    // Assert
    expect(verifyChain())->toBe(['passed' => false, 'seq' => 2, 'reason' => 'hash_mismatch']);
});

it('finds a changed record whose hash was recomputed, at the record after it', function () {
    // Arrange
    shelve(3);

    // Act
    DB::table('larasell_audit_log')->where('seq', 2)->update(['actor_id' => 'someone-else']);
    $tampered = (array) DB::table('larasell_audit_log')->where('seq', 2)->first();
    DB::table('larasell_audit_log')->where('seq', 2)->update(['hash' => app(AuditChain::class)->hash($tampered)]);

    // Assert
    expect(verifyChain())->toBe(['passed' => false, 'seq' => 3, 'reason' => 'link_mismatch']);
});

it('finds a deleted record', function (int $deleted, array $expected) {
    // Arrange
    shelve(3);

    // Act
    DB::table('larasell_audit_log')->where('seq', $deleted)->delete();

    // Assert
    expect(verifyChain())->toBe($expected);
})->with([
    'from the middle' => [2, ['passed' => false, 'seq' => 3, 'reason' => 'missing_event']],
    'the oldest, without a prune record' => [1, ['passed' => false, 'seq' => 2, 'reason' => 'missing_event']],
    'the newest' => [3, ['passed' => false, 'seq' => 3, 'reason' => 'head_mismatch']],
]);

it('passes an empty log', function () {
    // Act & Assert
    expect(verifyChain())->toBe(['passed' => true, 'seq' => null, 'reason' => null]);
});

it('reports the verification on the command line', function () {
    // Arrange
    shelve(2);

    // Act & Assert
    $this->artisan('larasell:verify-audit-log')
        ->expectsOutputToContain('2 audit records verified')
        ->assertSuccessful();

    DB::table('larasell_audit_log')->where('seq', 1)->update(['event' => 'book.burned']);

    $this->artisan('larasell:verify-audit-log')
        ->expectsOutputToContain('hash_mismatch')
        ->assertFailed();
});

it('reports a record it can\'t read as a break, instead of failing', function (array $corruption) {
    // Arrange
    shelve(3);

    // Act
    DB::table('larasell_audit_log')->where('seq', 2)->update($corruption);

    // Assert
    expect(verifyChain())->toBe(['passed' => false, 'seq' => 2, 'reason' => 'unreadable_record']);
})->with([
    'properties that aren\'t JSON' => [['properties' => '{"title": "Dune"']],
    'a date that isn\'t one' => [['occurred_at' => 'yesterday-ish']],
]);

it('refuses a record the database stores differently from how it was hashed', function () {
    // Arrange
    // As a database that silently truncates or rewrites a column would.
    DB::statement("CREATE TRIGGER mangle AFTER INSERT ON larasell_audit_log BEGIN UPDATE larasell_audit_log SET actor_id = 'mangled' WHERE id = NEW.id; END");

    // Act & Assert
    expect(fn () => shelve())->toThrow(AuditFailed::class);

    expect(DB::table('larasell_audit_log')->count())->toBe(0)
        ->and(verifyChain()['passed'])->toBeTrue();
});

it('refuses a record that already has its place in the chain', function (string $column) {
    // Arrange
    shelve();
    $record = (array) DB::table('larasell_audit_log')->sole();
    unset($record['seq'], $record['previous_hash'], $record['hash']);
    $record = [...$record, 'id' => (string) str()->ulid(), $column => $column === 'seq' ? 7 : str_repeat('a', 64)];

    // Act & Assert
    expect(fn () => DB::transaction(fn () => app(AuditChain::class)->append($record)))->toThrow(AuditFailed::class);

    expect(DB::table('larasell_audit_log')->count())->toBe(1);
})->with(['seq', 'previous_hash', 'hash']);

it('refuses a fraction in a record\'s stored properties, which has no single way to be written', function () {
    // Arrange
    shelve();
    $record = (array) DB::table('larasell_audit_log')->sole();
    unset($record['seq'], $record['previous_hash'], $record['hash']);
    $record = [...$record, 'id' => (string) str()->ulid(), 'properties' => '{"price":{"amount":9.99}}'];

    // Act & Assert
    expect(fn () => DB::transaction(fn () => app(AuditChain::class)->append($record)))->toThrow(AuditFailed::class, '[price.amount]');

    expect(DB::table('larasell_audit_log')->count())->toBe(1);
});
