<?php

namespace Larasell\Larasell\Customers\AuditRecords;

use Larasell\Larasell\Audit\AuditRecord;
use Larasell\Larasell\Audit\Enums\AuditCategory;
use Larasell\Larasell\Audit\Subject;
use Larasell\Larasell\Customers\CustomerId;

/**
 * A change to a customer or to what belongs to it, such as an address or a group membership.
 *
 * @internal Recorded from the core's customer events by AuditCustomerChanges. Its event names,
 * e.g. customer.address_added, are stable.
 */
class CustomerAudited extends AuditRecord
{
    /** @param array<string, mixed> $properties */
    public function __construct(
        private readonly string $event,
        private readonly CustomerId $customerId,
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
        return new Subject('customer', $this->customerId->value);
    }

    public function properties(): array
    {
        return $this->properties;
    }
}
