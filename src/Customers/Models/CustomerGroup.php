<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Translatable;

/**
 * A commercial group such as Retail or Wholesale. Other modules may read it as an input to their
 * rules; the group itself holds no prices or discounts.
 *
 * @property int $id
 * @property string $code
 * @property Translatable $name
 * @property string|null $description
 */
class CustomerGroup extends Model
{
    protected $table = 'larasell_customer_groups';

    protected $guarded = [];

    protected $casts = [
        'name' => TranslatableCast::class,
    ];

    /** @return BelongsToMany<Customer, $this, CustomerGroupMembership, 'membership'> */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'larasell_customer_group_memberships', 'customer_group_id', 'customer_id')
            ->using(CustomerGroupMembership::class)
            ->as('membership')
            ->withTimestamps();
    }
}
