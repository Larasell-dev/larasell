<?php

namespace Larasell\Larasell\SalesChannels\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\SalesChannels\Events\SalesChannelCreated as CoreEvent;

/**
 * A sales channel was created, with its key and type.
 *
 * @internal Recorded from the core's event. Its event name, sales_channel.created, is stable.
 */
class SalesChannelCreated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'sales_channel.created';
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
        return ['key' => $this->event->key, 'type' => $this->event->type];
    }
}
