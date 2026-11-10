<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A customer's place in a group.
 *
 * @property int $customer_id
 * @property int $customer_group_id
 */
class CustomerGroupMembership extends Pivot
{
    protected $table = 'larasell_customer_group_memberships';

    public $incrementing = true;
}
