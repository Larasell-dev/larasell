<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Events\ProductAttributeCreated as CoreEvent;

/**
 * An attribute was added, with its slug and name per locale.
 *
 * @internal Recorded from the core's event. Its event name, product_attribute.created, is stable.
 */
class ProductAttributeCreated extends AuditRecord
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
        return 'product_attribute.created';
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
        return ['slug' => $this->slug, 'name' => $this->name];
    }
}
