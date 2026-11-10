<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\EmailAddress;
use Larasell\Larasell\PhoneNumber;

/**
 * A person who acts for a company customer. A contact needs no login; accounts may point at one.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $first_name
 * @property string $last_name
 * @property EmailAddress|null $email
 * @property PhoneNumber|null $phone
 * @property string|null $job_title
 */
class CustomerContact extends Model
{
    protected $table = 'larasell_customer_contacts';

    protected $guarded = [];

    protected $casts = [
        'email' => EmailAddress::class,
        'phone' => PhoneNumber::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return HasMany<CustomerAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(CustomerAccount::class, 'contact_id');
    }
}
