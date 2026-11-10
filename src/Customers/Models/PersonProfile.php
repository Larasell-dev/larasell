<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The person an individual customer is.
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $salutation
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 */
class PersonProfile extends Model
{
    protected $table = 'larasell_customer_person_profiles';

    protected $guarded = [];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
