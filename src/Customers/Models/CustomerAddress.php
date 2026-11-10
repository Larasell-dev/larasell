<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\PhoneNumber;

/**
 * One of a customer's saved addresses. Orders copy what they use, so changing one leaves past
 * orders as they were.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $company
 * @property list<string> $street
 * @property string $postcode
 * @property string $city
 * @property string|null $region
 * @property Country $country_code
 * @property PhoneNumber|null $phone
 * @property bool $is_default_billing
 * @property bool $is_default_shipping
 */
class CustomerAddress extends Model
{
    protected $table = 'larasell_customer_addresses';

    protected $guarded = [];

    protected $casts = [
        'phone' => PhoneNumber::class,
        'street' => 'array',
        'country_code' => Country::class,
        'is_default_billing' => 'boolean',
        'is_default_shipping' => 'boolean',
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
