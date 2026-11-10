<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Customers\Enums\CustomerSource;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use Larasell\Larasell\Customers\Enums\CustomerType;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/**
 * Who a customer is in commerce: a person or a company, with or without a login. Logins stay with
 * the app; CustomerAccount only says which of the app's users act for the customer.
 *
 * @property int $id
 * @property string $customer_number
 * @property CustomerType $type
 * @property CustomerStatus $status
 * @property CustomerSource $source
 * @property Locale|null $preferred_locale
 * @property Currency|null $preferred_currency
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read PersonProfile|null $personProfile
 * @property-read CompanyProfile|null $companyProfile
 * @property-read CustomerAddress|null $defaultBillingAddress
 * @property-read CustomerAddress|null $defaultShippingAddress
 */
class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'larasell_customers';

    protected $guarded = [];

    protected $attributes = [
        'status' => 'active',
    ];

    protected $casts = [
        'type' => CustomerType::class,
        'status' => CustomerStatus::class,
        'source' => CustomerSource::class,
        'preferred_locale' => Locale::class,
        'preferred_currency' => Currency::class,
    ];

    /** The person's full name or the company's legal name. */
    public function displayName(): string
    {
        return match ($this->type) {
            CustomerType::Individual => trim(($this->personProfile->first_name ?? '').' '.($this->personProfile->last_name ?? '')),
            CustomerType::Company => $this->companyProfile->legal_name ?? '',
        };
    }

    /** @return HasOne<PersonProfile, $this> */
    public function personProfile(): HasOne
    {
        return $this->hasOne(PersonProfile::class, 'customer_id');
    }

    /** @return HasOne<CompanyProfile, $this> */
    public function companyProfile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class, 'customer_id');
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function defaultBillingAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'customer_id')->where('is_default_billing', true);
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function defaultShippingAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'customer_id')->where('is_default_shipping', true);
    }

    /** @return HasMany<CustomerContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class, 'customer_id');
    }

    /** @return HasMany<CustomerAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(CustomerAccount::class, 'customer_id');
    }

    /**
     * Every group the customer is in.
     *
     * @return BelongsToMany<CustomerGroup, $this, CustomerGroupMembership, 'membership'>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'larasell_customer_group_memberships', 'customer_id', 'customer_group_id')
            ->using(CustomerGroupMembership::class)
            ->as('membership')
            ->withTimestamps();
    }
}
