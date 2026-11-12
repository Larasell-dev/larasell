<?php

namespace Larasell\Larasell\Catalog\Options\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Catalog\Options\AuditRecords\OptionDefinitionCreated as OptionCreated;
use Larasell\Larasell\Catalog\Options\AuditRecords\OptionDefinitionDeleted as OptionDeleted;
use Larasell\Larasell\Catalog\Options\AuditRecords\OptionDefinitionUpdated as OptionUpdated;
use Larasell\Larasell\Catalog\Options\AuditRecords\OptionDefinitionValueCreated as ValueCreated;
use Larasell\Larasell\Catalog\Options\AuditRecords\OptionDefinitionValueDeleted as ValueDeleted;
use Larasell\Larasell\Catalog\Options\AuditRecords\OptionDefinitionValueUpdated as ValueUpdated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionCreated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionDeleted;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionUpdated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueCreated;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueDeleted;
use Larasell\Larasell\Catalog\Options\Events\OptionDefinitionValueUpdated;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;

/**
 * Audits the core's option events, in the core action's transaction, so a change that can't be
 * audited rolls back.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditOptionChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(OptionDefinitionCreated $event): void
    {
        // Read back, so the record says what was stored.
        $option = OptionDefinition::query()->findOrFail($event->definitionId->value);

        $this->auditor->record(new OptionCreated($event, $option->slug, $option->name->all()), $event->origin);
    }

    public function updated(OptionDefinitionUpdated $event): void
    {
        $this->auditor->record(new OptionUpdated($event), $event->origin);
    }

    public function deleted(OptionDefinitionDeleted $event): void
    {
        $this->auditor->record(new OptionDeleted($event), $event->origin);
    }

    public function valueCreated(OptionDefinitionValueCreated $event): void
    {
        $value = OptionDefinitionValue::query()->findOrFail($event->valueId->value);

        $this->auditor->record(new ValueCreated($event, $value->slug, $value->name->all()), $event->origin);
    }

    public function valueUpdated(OptionDefinitionValueUpdated $event): void
    {
        $this->auditor->record(new ValueUpdated($event), $event->origin);
    }

    public function valueDeleted(OptionDefinitionValueDeleted $event): void
    {
        $this->auditor->record(new ValueDeleted($event), $event->origin);
    }
}
