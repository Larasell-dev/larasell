<?php

namespace Larasell\Larasell\Storefront\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Storefront\AuditRecords\EntryPointAdded;
use Larasell\Larasell\Storefront\AuditRecords\EntryPointDeleted as EntryPointDeletedRecord;
use Larasell\Larasell\Storefront\Events\EntryPointCreated;
use Larasell\Larasell\Storefront\Events\EntryPointDeleted;

/**
 * Audits entry points being added and deleted, in the action's transaction.
 *
 * @internal Registered by StorefrontServiceProvider.
 */
class AuditEntryPointChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(EntryPointCreated $event): void
    {
        $this->auditor->record(new EntryPointAdded($event), $event->origin);
    }

    public function deleted(EntryPointDeleted $event): void
    {
        $this->auditor->record(new EntryPointDeletedRecord($event), $event->origin);
    }
}
