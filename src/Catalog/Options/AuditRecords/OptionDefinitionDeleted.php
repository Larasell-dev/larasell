<?php

namespace Larasell\Larasell\Catalog\Options\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionDeleted as CoreEvent;
use Larasell\Larasell\Catalog\Options\OptionDefinitionValueId;

/**
 * An option was deleted with its values, which no variant used.
 *
 * @internal Recorded from the core's event. Its event name, option_definition.deleted, is stable.
 */
class OptionDefinitionDeleted extends AuditRecord
{
    public function __construct(private readonly CoreEvent $event) {}

    public function event(): string
    {
        return 'option_definition.deleted';
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
        return ['values' => array_map(fn (OptionDefinitionValueId $id): int => $id->value, $this->event->valueIds)];
    }
}
