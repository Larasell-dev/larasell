<?php

namespace Larasell\Larasell\Catalog\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Catalog\AuditRecords\ProductAttributeCreated as AttributeCreated;
use Larasell\Larasell\Catalog\AuditRecords\ProductAttributeDeleted as AttributeDeleted;
use Larasell\Larasell\Catalog\AuditRecords\ProductAttributeUpdated as AttributeUpdated;
use Larasell\Larasell\Catalog\AuditRecords\ProductAttributeValueCreated as ValueCreated;
use Larasell\Larasell\Catalog\AuditRecords\ProductAttributeValueDeleted as ValueDeleted;
use Larasell\Larasell\Catalog\AuditRecords\ProductAttributeValueUpdated as ValueUpdated;
use Larasell\Larasell\Catalog\Events\ProductAttributeCreated;
use Larasell\Larasell\Catalog\Events\ProductAttributeDeleted;
use Larasell\Larasell\Catalog\Events\ProductAttributeUpdated;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueCreated;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueDeleted;
use Larasell\Larasell\Catalog\Events\ProductAttributeValueUpdated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

/**
 * Audits the core's attribute events, in the core action's transaction, so a change that can't be
 * audited rolls back.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditAttributeChanges
{
    public function __construct(private readonly Auditor $auditor) {}

    public function created(ProductAttributeCreated $event): void
    {
        // Read back, so the record says what was stored.
        $attribute = ProductAttribute::query()->findOrFail($event->attributeId->value);

        $this->auditor->record(new AttributeCreated($event, $attribute->slug, $attribute->name->all()), $event->origin);
    }

    public function updated(ProductAttributeUpdated $event): void
    {
        $this->auditor->record(new AttributeUpdated($event), $event->origin);
    }

    public function deleted(ProductAttributeDeleted $event): void
    {
        $this->auditor->record(new AttributeDeleted($event), $event->origin);
    }

    public function valueCreated(ProductAttributeValueCreated $event): void
    {
        $value = ProductAttributeValue::query()->findOrFail($event->valueId->value);

        $this->auditor->record(new ValueCreated($event, $value->slug, $value->name->all()), $event->origin);
    }

    public function valueUpdated(ProductAttributeValueUpdated $event): void
    {
        $this->auditor->record(new ValueUpdated($event), $event->origin);
    }

    public function valueDeleted(ProductAttributeValueDeleted $event): void
    {
        $this->auditor->record(new ValueDeleted($event), $event->origin);
    }
}
