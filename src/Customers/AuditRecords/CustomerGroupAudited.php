<?php

namespace Larasell\Larasell\Customers\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Customers\CustomerGroupId;

/**
 * A customer group was created, changed or deleted.
 *
 * @internal Recorded from the core's customer events by AuditCustomerChanges. Its event names,
 * e.g. customer_group.created, are stable.
 */
class CustomerGroupAudited extends AuditRecord
{
    /** @param array<string, mixed> $properties */
    public function __construct(
        private readonly string $event,
        private readonly CustomerGroupId $groupId,
        private readonly array $properties = [],
    ) {}

    public function event(): string
    {
        return $this->event;
    }

    public function category(): AuditCategory
    {
        return AuditCategory::BusinessCritical;
    }

    public function subject(): Subject
    {
        return new Subject('customer_group', $this->groupId->value);
    }

    public function properties(): array
    {
        return $this->properties;
    }
}
