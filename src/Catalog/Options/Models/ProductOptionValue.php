<?php

namespace Larasell\Larasell\Catalog\Options\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A value of a definition that a product offers for one of its options.
 *
 * @property int $id
 * @property int $product_option_id
 * @property int $option_definition_value_id
 * @property int $position
 * @property ProductOption $option
 * @property OptionDefinitionValue $definitionValue
 */
class ProductOptionValue extends Model
{
    public $timestamps = false;

    protected $table = 'larasell_product_option_values';

    protected $guarded = [];

    protected $casts = ['position' => 'integer'];

    /** @return BelongsTo<ProductOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /** @return BelongsTo<OptionDefinitionValue, $this> */
    public function definitionValue(): BelongsTo
    {
        return $this->belongsTo(OptionDefinitionValue::class, 'option_definition_value_id');
    }
}
