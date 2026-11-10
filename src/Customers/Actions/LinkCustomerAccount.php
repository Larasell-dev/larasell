<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Customers\CustomerAccountId;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use Larasell\Larasell\Customers\Events\CustomerAccountLinked;
use Larasell\Larasell\Customers\Exceptions\AccountAlreadyLinked;
use Larasell\Larasell\Customers\Exceptions\ContactBelongsToAnotherCustomer;
use Larasell\Larasell\Customers\Exceptions\CustomerNotActive;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerAccount;
use Larasell\Larasell\Customers\Models\CustomerContact;

/**
 * Lets one of the app's users act for a customer, optionally as one of its contacts. Call it from
 * the app's own registration or sign-in flow; a user may act for several customers.
 */
final readonly class LinkCustomerAccount
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerId $customerId, Model&Authenticatable $user, ?CustomerContactId $contactId = null): CustomerAccount
    {
        return $this->db->transaction(function () use ($customerId, $user, $contactId): CustomerAccount {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId->value);

            if ($customer->status !== CustomerStatus::Active) {
                throw CustomerNotActive::cannotLinkAccounts($customerId, $customer->status);
            }

            if ($contactId !== null && CustomerContact::query()->findOrFail($contactId->value)->customer_id !== $customer->id) {
                throw ContactBelongsToAnotherCustomer::forAccount($contactId, $customerId);
            }

            if ($customer->accounts()->whereMorphedTo('account', $user)->exists()) {
                throw AccountAlreadyLinked::toCustomer($customerId);
            }

            $account = $customer->accounts()->make(['contact_id' => $contactId?->value]);
            $account->account()->associate($user);
            $account->save();

            CustomerAccountLinked::dispatch(CustomerAccountId::of($account), $customerId);

            return $account;
        });
    }
}
