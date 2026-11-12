<?php

namespace Larasell\Larasell\Catalog\Options\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueUpdated as CoreEvent;

/**
 * An option's value changed: which fields.
 *
 * @internal Recorded from the core's event. Its event name, option_definition_value.updated, is stable.
 */
class OptionDefinitionValueUpdated extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'option_definition_value.updated';
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('option_definition_value', $this->event->valueId->value);
    }

    public function properties(): array
    {
        return ['option' => $this->event->definitionId->value, 'changed' => $this->event->changed];
    }
}
