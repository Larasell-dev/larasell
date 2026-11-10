<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Links one of the app's users to a customer, and optionally to the contact they are. The user and
 * how it signs in belong to the app; no credentials are stored here.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $contact_id
 * @property string $account_type
 * @property int $account_id
 * @property-read Model|null $account
 */
class CustomerAccount extends Model
{
    protected $table = 'larasell_customer_accounts';

    protected $guarded = [];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** @return BelongsTo<CustomerContact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerContact::class, 'contact_id');
    }

    /** @return MorphTo<Model, $this> */
    public function account(): MorphTo
    {
        return $this->morphTo();
    }
}
