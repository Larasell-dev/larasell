<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerContactRemoved;
use Larasell\Larasell\Customers\Models\CustomerAccount;
use Larasell\Larasell\Customers\Models\CustomerContact;

/** Removes a contact. Accounts linked to it stay linked to the customer, without a contact. */
final readonly class RemoveCustomerContact
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerContactId $contactId): void
    {
        $this->db->transaction(function () use ($contactId): void {
            $contact = CustomerContact::query()->findOrFail($contactId->value);
            // Done here as well as by the foreign key, which SQLite only honours with foreign keys on.
            CustomerAccount::query()->where('contact_id', $contact->id)->update(['contact_id' => null]);
            $contact->delete();

            CustomerContactRemoved::dispatch($contactId, new CustomerId($contact->customer_id));
        });
    }
}
