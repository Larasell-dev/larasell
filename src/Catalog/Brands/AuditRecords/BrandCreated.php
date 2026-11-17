<?php

namespace Larasell\Larasell\Catalog\Brands\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Brands\Events\BrandCreated as CoreEvent;

/**
 * A brand was added, with its slug and name per locale.
 *
 * @internal Recorded from the core's event. Its event name, brand.created, is stable.
 */
class BrandCreated extends AuditRecord
{
    /** @param  array<string, mixed>  $fields  The stored fields, read back after the change. */
    public function __construct(
        private readonly CoreEvent $event,
        private readonly array $fields,
    ) {}

    public function event(): string
    {
        return 'brand.created';
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
        return $this->fields;
    }
}
