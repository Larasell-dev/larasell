<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Events\ProductAttributeUpdated as CoreEvent;

/**
 * An attribute changed: which fields.
 *
 * @internal Recorded from the core's event. Its event name, product_attribute.updated, is stable.
 */
class ProductAttributeUpdated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'product_attribute.updated';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('product_attribute', $this->event->attributeId->value);
    }

    public function properties(): array
    {
        return ['changed' => $this->event->changed];
    }
}
