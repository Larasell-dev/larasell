<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerType;
use Larasell\Larasell\Customers\Events\CustomerContactAdded;
use Larasell\Larasell\Customers\Exceptions\ContactsNeedACompany;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerContact;

final readonly class AddCustomerContact
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, CustomerContactPayload $payload, Origin $origin): CustomerContact
    {
        return $this->db->transaction(function () use ($origin, $customerId, $payload): CustomerContact {
            $customer = Customer::query()->findOrFail($customerId->value);

            if ($customer->type !== CustomerType::Company) {
                throw ContactsNeedACompany::forCustomer($customerId);
            }

            $contact = $customer->contacts()->create($payload->toAttributes());
            CustomerContactAdded::dispatch(CustomerContactId::of($contact), $customerId, $origin);

            return $contact;
        });
    }
}
