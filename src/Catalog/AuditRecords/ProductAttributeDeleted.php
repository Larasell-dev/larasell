<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductAttributeDeleted as CoreEvent;

/**
 * An attribute was deleted with its values, which products and variants lost.
 *
 * @internal Recorded from the core's event. Its event name, product_attribute.deleted, is stable.
 */
class ProductAttributeDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'product_attribute.deleted';
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
        return ['values' => array_map(fn (AttributeValueId $id): int => $id->value, $this->event->valueIds)];
    }
}
