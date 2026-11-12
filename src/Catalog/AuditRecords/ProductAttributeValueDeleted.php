<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueDeleted as CoreEvent;

/**
 * An attribute's value was deleted, which products and variants lost.
 *
 * @internal Recorded from the core's event. Its event name, product_attribute_value.deleted, is stable.
 */
class ProductAttributeValueDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'product_attribute_value.deleted';
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
        return ['attribute' => $this->event->attributeId->value];
    }
}
