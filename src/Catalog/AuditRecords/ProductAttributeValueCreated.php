<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueCreated as CoreEvent;

/**
 * A value was added to an attribute, with its slug and name per locale.
 *
 * @internal Recorded from the core's event. Its event name, product_attribute_value.created, is stable.
 */
class ProductAttributeValueCreated extends AuditRecord
{
    /**
     * @param  array<string, string>  $name  The stored name per locale, read back after the change.
     */
    public function __construct(
        private readonly CoreEvent $event,
        private readonly string $slug,
        private readonly array $name,
    ) {}

    public function event(): string
    {
        return 'product_attribute_value.created';
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
        return ['attribute' => $this->event->attributeId->value, 'slug' => $this->slug, 'name' => $this->name];
    }
}
