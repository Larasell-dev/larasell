<?php

namespace Larasell\Larasell\Catalog\Options\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An option definition a product uses to tell its variants apart.
 *
 * @property int $id
 * @property int $product_id
 * @property int $option_definition_id
 * @property int $position
 * @property OptionDefinition $definition
 */
class ProductOption extends Model
{
    public $timestamps = false;

    protected $table = 'larasell_product_options';

    protected $guarded = [];

    protected $casts = ['position' => 'integer'];

    /** @return BelongsTo<OptionDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(OptionDefinition::class, 'option_definition_id');
    }

    /** @return HasMany<ProductOptionValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('position')->orderBy('id');
    }
}
