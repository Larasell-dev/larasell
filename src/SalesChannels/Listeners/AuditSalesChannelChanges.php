<?php

namespace Larasell\Larasell\SalesChannels\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\SalesChannels\AuditRecords\SalesChannelCreated as Created;
use Larasell\Larasell\SalesChannels\AuditRecords\SalesChannelDeleted as Deleted;
use Larasell\Larasell\SalesChannels\AuditRecords\SalesChannelUpdated as Updated;
use Larasell\Larasell\SalesChannels\Events\SalesChannelCreated;
use Larasell\Larasell\SalesChannels\Events\SalesChannelDeleted;
use Larasell\Larasell\SalesChannels\Events\SalesChannelUpdated;

/**
 * Audits the core's sales channel events, in the core action's transaction.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditSalesChannelChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(SalesChannelCreated $event): void
    {
        $this->auditor->record(new Created($event), $event->origin);
    }

    public function updated(SalesChannelUpdated $event): void
    {
        $this->auditor->record(new Updated($event), $event->origin);
    }

    public function deleted(SalesChannelDeleted $event): void
    {
        $this->auditor->record(new Deleted($event), $event->origin);
    }
}
