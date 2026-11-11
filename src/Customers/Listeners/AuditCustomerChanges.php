<?php

namespace Larasell\Larasell\Customers\Listeners;

use Larasell\Larasell\Audit\Auditor;
use Larasell\Larasell\Customers\AuditRecords\CustomerAudited;
use Larasell\Larasell\Customers\AuditRecords\CustomerGroupAudited;
use Larasell\Larasell\Customers\Events\CustomerAccountLinked;
use Larasell\Larasell\Customers\Events\CustomerAccountUnlinked;
use Larasell\Larasell\Customers\Events\CustomerAddressAdded;
use Larasell\Larasell\Customers\Events\CustomerAddressDeleted;
use Larasell\Larasell\Customers\Events\CustomerAddressUpdated;
use Larasell\Larasell\Customers\Events\CustomerContactAdded;
use Larasell\Larasell\Customers\Events\CustomerContactRemoved;
use Larasell\Larasell\Customers\Events\CustomerContactUpdated;
use Larasell\Larasell\Customers\Events\CustomerCreated;
use Larasell\Larasell\Customers\Events\CustomerGroupCreated;
use Larasell\Larasell\Customers\Events\CustomerGroupDeleted;
use Larasell\Larasell\Customers\Events\CustomerGroupUpdated;
use Larasell\Larasell\Customers\Events\CustomerJoinedGroup;
use Larasell\Larasell\Customers\Events\CustomerLeftGroup;
use Larasell\Larasell\Customers\Events\CustomerStatusChanged;
use Larasell\Larasell\Customers\Events\CustomerUpdated;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerGroup;

/**
 * Audits the core's customer events, in the core action's transaction, so a change that can't be
 * audited rolls back. The action's caller names who made the change.
 *
 * @internal Registered by LarasellServiceProvider.
 */
class AuditCustomerChanges
{
    /** The core events it audits. */
    public const array EVENTS = [
        CustomerCreated::class,
        CustomerUpdated::class,
        CustomerStatusChanged::class,
        CustomerAddressAdded::class,
        CustomerAddressUpdated::class,
        CustomerAddressDeleted::class,
        CustomerContactAdded::class,
        CustomerContactUpdated::class,
        CustomerContactRemoved::class,
        CustomerAccountLinked::class,
        CustomerAccountUnlinked::class,
        CustomerJoinedGroup::class,
        CustomerLeftGroup::class,
        CustomerGroupCreated::class,
        CustomerGroupUpdated::class,
        CustomerGroupDeleted::class,
    ];

    public function __construct(private readonly Auditor $auditor) {}

    public function handle(object $event): void
    {
        $record = match (true) {
            $event instanceof CustomerCreated => $this->created($event),
            $event instanceof CustomerUpdated => new CustomerAudited('customer.updated', $event->customerId),
            $event instanceof CustomerStatusChanged => new CustomerAudited('customer.status_changed', $event->customerId, ['from' => $event->from->value, 'to' => $event->to->value]),
            $event instanceof CustomerAddressAdded => new CustomerAudited('customer.address_added', $event->customerId, ['address_id' => $event->addressId->value]),
            $event instanceof CustomerAddressUpdated => new CustomerAudited('customer.address_updated', $event->customerId, ['address_id' => $event->addressId->value]),
            $event instanceof CustomerAddressDeleted => new CustomerAudited('customer.address_deleted', $event->customerId, ['address_id' => $event->addressId->value]),
            $event instanceof CustomerContactAdded => new CustomerAudited('customer.contact_added', $event->customerId, ['contact_id' => $event->contactId->value]),
            $event instanceof CustomerContactUpdated => new CustomerAudited('customer.contact_updated', $event->customerId, ['contact_id' => $event->contactId->value]),
            $event instanceof CustomerContactRemoved => new CustomerAudited('customer.contact_removed', $event->customerId, ['contact_id' => $event->contactId->value]),
            $event instanceof CustomerAccountLinked => new CustomerAudited('customer.account_linked', $event->customerId, ['account_id' => $event->accountId->value]),
            $event instanceof CustomerAccountUnlinked => new CustomerAudited('customer.account_unlinked', $event->customerId, ['account_id' => $event->accountId->value]),
            $event instanceof CustomerJoinedGroup => new CustomerAudited('customer.joined_group', $event->customerId, ['group_id' => $event->groupId->value]),
            $event instanceof CustomerLeftGroup => new CustomerAudited('customer.left_group', $event->customerId, ['group_id' => $event->groupId->value]),
            // Read back, so the record says what was stored.
            $event instanceof CustomerGroupCreated => new CustomerGroupAudited('customer_group.created', $event->groupId, ['code' => CustomerGroup::query()->findOrFail($event->groupId->value)->code]),
            $event instanceof CustomerGroupUpdated => new CustomerGroupAudited('customer_group.updated', $event->groupId, ['code' => CustomerGroup::query()->findOrFail($event->groupId->value)->code]),
            // The group is gone by now; its code is in the records of its creation and changes.
            $event instanceof CustomerGroupDeleted => new CustomerGroupAudited('customer_group.deleted', $event->groupId),
            default => null,
        };

        if ($record !== null) {
            $this->auditor->record($record, $event->origin);
        }
    }

    private function created(CustomerCreated $event): CustomerAudited
    {
        $customer = Customer::query()->findOrFail($event->customerId->value);

        return new CustomerAudited('customer.created', $event->customerId, ['customer_number' => $customer->customer_number, 'type' => $customer->type->value]);
    }
}
