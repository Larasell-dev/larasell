<?php

namespace Larasell\Larasell\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Url;

/**
 * The company a company customer is.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $legal_name
 * @property string|null $legal_form
 * @property string|null $company_number
 * @property string|null $vat_id
 * @property Country $country_code
 * @property string|null $industry
 * @property Url|null $website
 */
class CompanyProfile extends Model
{
    protected $table = 'larasell_customer_company_profiles';

    protected $guarded = [];

    protected $casts = [
        'website' => Url::class,
        'country_code' => Country::class,
    ];

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
