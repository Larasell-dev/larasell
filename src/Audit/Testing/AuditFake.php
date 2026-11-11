<?php

namespace Larasell\Larasell\Audit\Testing;

use Illuminate\Support\Testing\Fakes\Fake;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Chain\AuditChain;
use Larasell\Larasell\Audit\Exceptions\AuditFailed;
use Larasell\Larasell\Audit\Origin;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * An Auditor that keeps records in memory, made by Audit::fake(). It runs the same checks as the
 * real one; only storing them differs.
 *
 * @public Through the Audit facade.
 */
class AuditFake extends Auditor implements Fake
{
    /**
     * @var list<array{record: AuditRecord, origin: Origin}>
     */
    private array $recorded = [];

    private bool $failing = false;

    /**
     * @param  list<class-string<AuditRecord>>  $recordsToFake  Empty fakes all of them.
     */
    public function __construct(public readonly Auditor $auditor, AuditChain $chain, private readonly array $recordsToFake = [])
    {
        parent::__construct($chain);

        // What apps and modules registered with the real auditor while the app booted.
        $this->actorTypes = $auditor->actorTypes;
    }

    /**
     * Makes every faked record fail to store, so tests can check the change rolls back.
     */
    public function failing(): static
    {
        $this->failing = true;

        return $this;
    }

    /**
     * @param  class-string<AuditRecord>  $record
     * @param  (callable(AuditRecord, Origin): bool)|null  $callback
     */
    public function assertRecorded(string $record, ?callable $callback = null): void
    {
        PHPUnit::assertNotEmpty($this->recorded($record, $callback), "The expected [{$record}] audit record was not recorded.");
    }

    /**
     * @param  class-string<AuditRecord>  $record
     */
    public function assertRecordedTimes(string $record, int $times = 1): void
    {
        $count = count($this->recorded($record));

        PHPUnit::assertSame($times, $count, "The expected [{$record}] audit record was recorded {$count} times instead of {$times} times.");
    }

    /**
     * @param  class-string<AuditRecord>  $record
     * @param  (callable(AuditRecord, Origin): bool)|null  $callback
     */
    public function assertNotRecorded(string $record, ?callable $callback = null): void
    {
        PHPUnit::assertEmpty($this->recorded($record, $callback), "The unexpected [{$record}] audit record was recorded.");
    }

    public function assertNothingRecorded(): void
    {
        $events = implode(', ', array_map(fn (array $entry) => $entry['record']->event(), $this->recorded));

        PHPUnit::assertEmpty($this->recorded, "Audit records were recorded unexpectedly: {$events}.");
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function store(AuditRecord $record, Origin $origin, array $row): void
    {
        if (! $this->shouldFake($record)) {
            parent::store($record, $origin, $row);

            return;
        }

        if ($this->failing) {
            throw AuditFailed::faked($record->event());
        }

        $this->recorded[] = ['record' => $record, 'origin' => $origin];
    }

    private function shouldFake(AuditRecord $record): bool
    {
        if ($this->recordsToFake === []) {
            return true;
        }

        foreach ($this->recordsToFake as $class) {
            if ($record instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string  $class
     * @param  (callable(AuditRecord, Origin): bool)|null  $callback
     * @return list<array{record: AuditRecord, origin: Origin}>
     */
    private function recorded(string $class, ?callable $callback = null): array
    {
        return array_values(array_filter($this->recorded, fn (array $entry) => $entry['record'] instanceof $class
            && ($callback === null || $callback($entry['record'], $entry['origin']))));
    }
}
