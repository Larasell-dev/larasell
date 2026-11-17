<?php

namespace Larasell\Larasell\Catalog\Brands\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Brands\Events\BrandDeleted as CoreEvent;

/**
 * A brand was deleted.
 *
 * @internal Recorded from the core's event. Its event name, brand.deleted, is stable.
 */
class BrandDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'brand.deleted';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('brand', $this->event->brandId->value);
    }

    public function properties(): array
    {
        return [];
    }
}
