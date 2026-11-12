<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueUpdated as CoreEvent;

/**
 * An attribute's value changed: which fields.
 *
 * @internal Recorded from the core's event. Its event name, product_attribute_value.updated, is stable.
 */
class ProductAttributeValueUpdated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'product_attribute_value.updated';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('product_attribute_value', $this->event->valueId->value);
    }

    public function properties(): array
    {
        return ['attribute' => $this->event->attributeId->value, 'changed' => $this->event->changed];
    }
}
