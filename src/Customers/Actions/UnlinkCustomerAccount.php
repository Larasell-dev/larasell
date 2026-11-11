<?php

namespace Larasell\Larasell\Customers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Customers\CustomerAccountId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerAccountUnlinked;
use Larasell\Larasell\Customers\Models\CustomerAccount;

/** Stops a user acting for a customer. The user and its login are the app's and stay as they are. */
final readonly class UnlinkCustomerAccount
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CustomerAccountId $accountId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $accountId): void {
            $account = CustomerAccount::query()->findOrFail($accountId->value);
            $account->delete();

            CustomerAccountUnlinked::dispatch($accountId, new CustomerId($account->customer_id), $origin);
        });
    }
}
