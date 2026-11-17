<?php

namespace Larasell\Larasell\Catalog\Manufacturers\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerCreated as CoreEvent;

/**
 * A manufacturer was added, with its name and country.
 *
 * @internal Recorded from the core's event. Its event name, manufacturer.created, is stable.
 */
class ManufacturerCreated extends AuditRecord
{
    /** @param  array<string, mixed>  $fields  The stored fields, read back after the change. */
    public function __construct(
        private readonly CoreEvent $event,
        private readonly array $fields,
    ) {}

    public function event(): string
    {
        return 'manufacturer.created';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('manufacturer', $this->event->manufacturerId->value);
    }

    public function properties(): array
    {
        return $this->fields;
    }
}
