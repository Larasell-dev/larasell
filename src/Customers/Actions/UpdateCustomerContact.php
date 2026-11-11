<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerContactUpdated;
use Larasell\Larasell\Customers\Models\CustomerContact;

final readonly class UpdateCustomerContact
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerContactId $contactId, CustomerContactPayload $payload, Origin $origin): CustomerContact
    {
        return $this->db->transaction(function () use ($origin, $contactId, $payload): CustomerContact {
            $contact = CustomerContact::query()->findOrFail($contactId->value);
            $contact->update($payload->toAttributes());
            CustomerContactUpdated::dispatch($contactId, new CustomerId($contact->customer_id), $origin);

            return $contact;
        });
    }
}
