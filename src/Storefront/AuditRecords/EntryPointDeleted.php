<?php

namespace Larasell\Larasell\Storefront\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Storefront\Events\EntryPointDeleted as CoreEvent;

/**
 * An entry point of a sales channel was deleted.
 *
 * @internal Recorded from the core's event. Its event name, sales_channel.entry_point_deleted, is stable.
 */
class EntryPointDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'sales_channel.entry_point_deleted';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('sales_channel', $this->event->channelId->value);
    }

    public function properties(): array
    {
        return ['entry_point_id' => $this->event->entryPointId->value];
    }
}
