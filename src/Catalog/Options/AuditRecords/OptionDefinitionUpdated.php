<?php

namespace Larasell\Larasell\Catalog\Options\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionUpdated as CoreEvent;

/**
 * An option changed: which fields.
 *
 * @internal Recorded from the core's event. Its event name, option_definition.updated, is stable.
 */
class OptionDefinitionUpdated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'option_definition.updated';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('option_definition', $this->event->definitionId->value);
    }

    public function properties(): array
    {
        return ['changed' => $this->event->changed];
    }
}
