<?php

namespace Larasell\Larasell\Audit\Facades;

use Illuminate\Support\Facades\Facade;
use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Chain\AuditChain;
use Larasell\Larasell\Audit\Testing\AuditFake;

/**
 * Records audit records, like an injected Auditor: `Audit::record(new ProductArchived($product), $origin)`.
 *
 * In tests, Audit::fake() keeps records in memory, to assert like Event::fake():
 * `Audit::assertRecorded(ProductArchived::class, fn ($record, Origin $origin) => ...)`.
 *
 * @method static void record(\Larasell\Larasell\Audit\AuditRecord $record, \Larasell\Larasell\Audit\Origin $origin)
 * @method static void assertRecorded(class-string<AuditRecord> $record, (callable(AuditRecord, \Larasell\Larasell\Audit\Origin): bool)|null $callback = null)
 * @method static void assertRecordedTimes(class-string<AuditRecord> $record, int $times = 1)
 * @method static void assertNotRecorded(class-string<AuditRecord> $record, (callable(AuditRecord, \Larasell\Larasell\Audit\Origin): bool)|null $callback = null)
 * @method static void assertNothingRecorded()
 *
 * @see Auditor
 * @see AuditFake
 *
 * @public
 */
class Audit extends Facade
{
    /**
     * Keeps the given records, or all of them, in memory instead of the audit log. The auditor's
     * checks still run, and the actor and source are resolved as they would be.
     *
     * @param  list<class-string<AuditRecord>>  $recordsToFake
     */
    public static function fake(array $recordsToFake = []): AuditFake
    {
        $root = static::getFacadeRoot();
        $real = $root instanceof AuditFake ? $root->auditor : $root;

        return tap(new AuditFake($real, app(AuditChain::class), $recordsToFake), static::swap(...));
    }

    protected static function getFacadeAccessor(): string
    {
        return Auditor::class;
    }
}
