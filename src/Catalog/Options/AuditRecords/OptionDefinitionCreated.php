<?php

namespace Larasell\Larasell\Catalog\Options\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionCreated as CoreEvent;

/**
 * An option was added, with its slug and name per locale.
 *
 * @internal Recorded from the core's event. Its event name, option_definition.created, is stable.
 */
class OptionDefinitionCreated extends AuditRecord
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
        return 'option_definition.created';
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
        return ['slug' => $this->slug, 'name' => $this->name];
    }
}
