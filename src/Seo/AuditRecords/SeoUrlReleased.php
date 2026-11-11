<?php

namespace Larasell\Larasell\Seo\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Seo\Events\SeoUrlReleased as Released;

/**
 * An old or gone URL was deleted: an admin removed it, or another page claimed its path.
 *
 * @internal Recorded by AuditSeoChanges. Its event name, seo.url_released, is stable.
 */
class SeoUrlReleased extends AuditRecord
{
    public function __construct(private readonly Released $event) {}

    public function event(): string
    {
        return 'seo.url_released';
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
            'status' => $this->event->status->value,
        ];
    }

    /**
     * The event is dispatched once committed, so the record is stored after the change.
     */
    public function requiresTransaction(): bool
    {
        return false;
    }
}
