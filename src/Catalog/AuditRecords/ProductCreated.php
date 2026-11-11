<?php

namespace Larasell\Larasell\Catalog\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Events\ProductCreated as CoreEvent;

/**
 * A product was created, with its name.
 *
 * @internal Recorded from the core's event. Its event name, product.created, is stable.
 */
class ProductCreated extends AuditRecord
{
    /**
     * @param  array<string, string>  $name  The stored name per locale, read back after the change.
     */
    public function __construct(private readonly CoreEvent $event, private readonly array $name) {}

    public function event(): string
    {
        return 'product.created';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('product', $this->event->productId->value);
    }

    public function properties(): array
    {
        return ['name' => $this->name];
    }
}
