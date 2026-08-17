<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use Larasell\Larasell\Enums\ProductOptionType;

/**
 * @property int $id
 * @property int $product_option_id
 * @property string $slug
 * @property string $name
 * @property mixed $value
 * @property int|null $position
 * @property ProductOption $option
 */
class ProductOptionValue extends Model
{
    use HasFactory;

    protected $table = 'larasell_product_option_values';

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $value): void {
            $value->assertValueMatchesOptionType();
        });
    }

    /**
     * @return Attribute<mixed, mixed>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value): mixed => $value === null ? null : json_decode($value, true),
            set: fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<ProductOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(
            $this->productOptionModel(),
            'product_option_id'
        );
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productModel(),
            'larasell_product_product_option_value',
            'product_option_value_id',
            'product_id'
        )->withTimestamps();
    }

    protected function productOptionModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.product_option', ProductOption::class)
            : ProductOption::class;
    }

    protected function productModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.product', Product::class)
            : Product::class;
    }

    private function assertValueMatchesOptionType(): void
    {
        $option = $this->relationLoaded('option')
            ? $this->option
            : $this->option()->first();

        if (! $option instanceof ProductOption) {
            return;
        }

        if ($option->type->accepts($this->value)) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Product option value must be %s.',
            $option->type->value
        ));
    }
}
