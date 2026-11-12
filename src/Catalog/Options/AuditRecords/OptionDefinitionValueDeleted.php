<?php

namespace Larasell\Larasell\Catalog\Options\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueDeleted as CoreEvent;

/**
 * An option's value was deleted, which no variant used.
 *
 * @internal Recorded from the core's event. Its event name, option_definition_value.deleted, is stable.
 */
class OptionDefinitionValueDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'option_definition_value.deleted';
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
        return ['option' => $this->event->definitionId->value];
    }
}
