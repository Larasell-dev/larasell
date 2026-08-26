<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Enums\ProductOptionType;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ProductOptionType $type
 */
class ProductOption extends Model
{
    use HasFactory;

    protected $table = 'larasell_product_options';

    protected $guarded = [];

    protected $casts = [
        'type' => ProductOptionType::class,
    ];

    /**
     * @return HasMany<ProductOptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(
            $this->productOptionValueModel(),
            'product_option_id'
        );
    }

    protected function productOptionValueModel(): string
    {
        return app(ModelRegistry::class)->productOptionValue->class();
    }
}
