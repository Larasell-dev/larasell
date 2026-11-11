<?php

namespace Larasell\Larasell\Storefront\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Storefront\Events\EntryPointCreated as CoreEvent;

/**
 * A sales channel got an entry point, with its locale and default currency.
 *
 * @internal Recorded from the core's event. Its event name, sales_channel.entry_point_added, is stable.
 */
class EntryPointAdded extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'sales_channel.entry_point_added';
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
        return [
            'entry_point_id' => $this->event->entryPointId->value,
            'locale' => $this->event->locale->value,
            'default_currency' => $this->event->defaultCurrency?->value,
        ];
    }
}
