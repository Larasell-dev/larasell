<?php

namespace Larasell\Larasell\Seo\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Seo\Events\SeoUrlRestored as Restored;

/**
 * An old URL became its page's current one again.
 *
 * @internal Recorded by AuditSeoChanges. Its event name, seo.url_restored, is stable.
 */
class SeoUrlRestored extends AuditRecord
{
    public function __construct(private readonly Restored $event) {}

    public function event(): string
    {
        return 'seo.url_restored';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject($this->event->resourceType, $this->event->resourceId);
    }

    public function properties(): array
    {
        return [
            'sales_channel_id' => $this->event->salesChannelId->value,
            'locale' => $this->event->locale,
            'path' => $this->event->path,
        ];
    }
}
