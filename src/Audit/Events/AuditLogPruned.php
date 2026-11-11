<?php

namespace Larasell\Larasell\Audit\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The audit log was pruned, e.g. so an app deletes its own expired copies of it. Dispatched once
 * committed, so a listener that fails can't keep the log from being pruned.
 *
 * @public
 */
final readonly class AuditLogPruned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $deleted) {}
}
