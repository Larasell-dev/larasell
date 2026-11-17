<?php

namespace Larasell\Larasell\Catalog\Brands\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Brands\Events\BrandUpdated as CoreEvent;

/**
 * A brand changed: which fields.
 *
 * @internal Recorded from the core's event. Its event name, brand.updated, is stable.
 */
class BrandUpdated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'brand.updated';
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
        return ['changed' => $this->event->changed];
    }
}
