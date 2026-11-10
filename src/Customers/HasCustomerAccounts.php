<?php

namespace Larasell\Larasell\Customers;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerAccount;

/**
 * For the app's user model: the customers the user acts for. Link users with LinkCustomerAccount.
 */
trait HasCustomerAccounts
{
    /** @return MorphMany<CustomerAccount, $this> */
    public function customerAccounts(): MorphMany
    {
        return $this->morphMany(CustomerAccount::class, 'account');
    }

    /** @return MorphToMany<Customer, $this> */
    public function customers(): MorphToMany
    {
        return $this->morphToMany(Customer::class, 'account', 'larasell_customer_accounts', 'account_id', 'customer_id')
            ->withPivot('contact_id')
            ->withTimestamps();
    }
}

/**
 * @internal So PHPStan analyses the trait.
 */
abstract class AuthenticatableCustomerAccountUser extends User
{
    use HasCustomerAccounts;
}
