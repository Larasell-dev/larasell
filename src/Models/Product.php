<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use Larasell\Larasell\Casts\NullableTranslatableCast;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $sku
 * @property string|null $barcode
 * @property Translatable $name
 * @property Translatable|null $description
 * @property Price $price
 * @property int|null $stock
 * @property int|null $min_quantity
 * @property int|null $max_quantity
 * @property bool $allow_backorders
 * @property Visibility $status
 *
 * @method static Builder<static> visible()
 * @method static Builder<static> withOptions()
 */
class Product extends Model
{
    use HasFactory;

    protected $table = 'larasell_products';

    protected $guarded = [];

    protected $casts = [
        'name' => TranslatableCast::class,
        'description' => NullableTranslatableCast::class,
        'price' => PriceCast::class,
        'stock' => 'integer',
        'allow_backorders' => 'boolean',
        'status' => Visibility::class,
    ];

    /**
     * @return Attribute<int|null, int|null>
     */
    protected function minQuantity(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?int => $value === null ? null : (int) $value,
            set: function (?int $value): ?int {
                if ($value !== null && $value < 1) {
                    throw new InvalidArgumentException('Product min quantity must be at least 1.');
                }

                $maxQuantity = $this->max_quantity;

                if ($value !== null && $maxQuantity !== null && $value > $maxQuantity) {
                    throw new InvalidArgumentException('Product min quantity cannot exceed max quantity.');
                }

                return $value;
            },
        );
    }

    /**
     * @return Attribute<int|null, int|null>
     */
    protected function maxQuantity(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?int => $value === null ? null : (int) $value,
            set: function (?int $value): ?int {
                if ($value !== null && $value < 1) {
                    throw new InvalidArgumentException('Product max quantity must be at least 1.');
                }

                $minQuantity = $this->min_quantity;

                if ($value !== null && $minQuantity !== null && $value < $minQuantity) {
                    throw new InvalidArgumentException('Product max quantity cannot be lower than min quantity.');
                }

                return $value;
            },
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('status', Visibility::Visible->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withOptions(Builder $query): Builder
    {
        return $query->with('optionValues.option');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->categoryModel(),
            'larasell_category_product',
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<ProductImage, $this>
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productImageModel(),
            'larasell_product_product_image',
            'product_id',
            'product_image_id'
        )->withPivot('position')->orderByPivot('position')->withTimestamps();
    }

    /**
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productOptionValueModel(),
            'larasell_product_product_option_value',
            'product_id',
            'product_option_value_id'
        )->withTimestamps();
    }

    protected function categoryModel(): string
    {
        return app(ModelRegistry::class)->category->class();
    }

    protected function productImageModel(): string
    {
        return app(ModelRegistry::class)->productImage->class();
    }

    protected function productOptionValueModel(): string
    {
        return app(ModelRegistry::class)->productOptionValue->class();
    }
}
