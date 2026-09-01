<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Larasell\Larasell\Casts\NullableTranslatableCast;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

/**
 * @property int $id
 * @property Translatable $slug
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
 * @method static Builder<static> inCategory(Category $category)
 * @method static Builder<static> inCategoryTree(Category $category)
 * @method static Builder<static> withAttributeValues()
 */
class Product extends Model
{
    use HasFactory;

    protected $table = 'larasell_products';

    protected $guarded = [];

    protected $casts = [
        'slug' => TranslatableCast::class,
        'name' => TranslatableCast::class,
        'description' => NullableTranslatableCast::class,
        'price' => PriceCast::class,
        'stock' => 'integer',
        'allow_backorders' => 'boolean',
        'status' => Visibility::class,
    ];

    protected static function booted(): void
    {
        static::created(function (Product $product): void {
            $product->variants()->create($product->defaultVariantAttributes());
        });

        static::updated(function (Product $product): void {
            if ($product->wasChanged([
                'sku',
                'barcode',
                'price',
                'stock',
                'allow_backorders',
                'min_quantity',
                'max_quantity',
                'status',
            ])) {
                $product->variants()->where('is_default', true)->first()?->update(
                    $product->defaultVariantAttributes(),
                );
            }
        });
    }

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
    protected function inCategory(Builder $query, Category $category): Builder
    {
        return $query->whereHas(
            'categories',
            fn (Builder $query): Builder => $query->whereKey($category->getKey()),
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inCategoryTree(Builder $query, Category $category): Builder
    {
        $category->loadMissing('descendants');

        $categoryIds = collect([$category->getKey()])
            ->merge($category->descendants->flatMap(
                fn (Category $descendant): Collection => $this->categoryTreeIds($descendant),
            ));

        return $query->whereHas(
            'categories',
            fn (Builder $query): Builder => $query->whereKey($categoryIds),
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withAttributeValues(Builder $query): Builder
    {
        return $query->with('attributeValues.attribute');
    }

    /**
     * @return Collection<int, int>
     */
    private function categoryTreeIds(Category $category): Collection
    {
        return collect([$category->getKey()])
            ->merge($category->descendants->flatMap(
                fn (Category $descendant): Collection => $this->categoryTreeIds($descendant),
            ));
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
     * @return BelongsToMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productAttributeValueModel(),
            'larasell_product_product_attribute_value',
            'product_id',
            'product_attribute_value_id'
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<ProductAttribute, $this>
     */
    public function variantDimensions(): BelongsToMany
    {
        return $this->belongsToMany(
            $this->productAttributeModel(),
            'larasell_product_variant_dimensions',
            'product_id',
            'product_attribute_id',
        )->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany($this->productVariantModel());
    }

    public function defaultVariant(): ProductVariant
    {
        /** @var ProductVariant|null $variant */
        $variant = $this->variants()->where('is_default', true)->first();

        if (! $variant instanceof ProductVariant) {
            throw new InvalidArgumentException('This product does not have a default variant. Select a product variant explicitly.');
        }

        return $variant;
    }

    /**
     * @param  array<int, mixed>  $dimensions
     * @return EloquentCollection<int, ProductVariant>
     */
    public function generateVariants(array $dimensions): EloquentCollection
    {
        $dimensions = collect($dimensions)->values();

        if ($dimensions->isEmpty()
            || $dimensions->contains(fn (mixed $dimension): bool => ! $dimension instanceof ProductAttribute || ! $dimension->exists)
            || $dimensions->pluck('id')->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException('Variant dimensions must be unique persisted product attributes.');
        }

        $values = $this->attributeValues()
            ->whereIn('product_attribute_id', $dimensions->pluck('id'))
            ->get()
            ->groupBy('product_attribute_id');

        if ($dimensions->contains(fn (ProductAttribute $dimension): bool => ! $values->has($dimension->id))) {
            throw new InvalidArgumentException('Every variant dimension must have values attached to the product.');
        }

        $combinations = $dimensions->reduce(
            fn (Collection $combinations, ProductAttribute $dimension): Collection => $combinations->flatMap(
                fn (array $combination): Collection => $values->get($dimension->id)->map(
                    fn (ProductAttributeValue $value): array => [...$combination, $value],
                ),
            ),
            collect([[]]),
        );

        if ($combinations->count() < 2) {
            throw new InvalidArgumentException('Variant generation must produce at least two combinations.');
        }

        return DB::transaction(function () use ($combinations, $dimensions): EloquentCollection {
            $this->variants()->where('is_default', true)->delete();

            $configuredDimensionIds = $this->variantDimensions()->pluck('larasell_product_attributes.id');
            $requestedDimensionIds = $dimensions->pluck('id');

            if ($this->variants()->exists()
                && $configuredDimensionIds->sort()->values()->all() !== $requestedDimensionIds->sort()->values()->all()) {
                throw new InvalidArgumentException('Variant dimensions cannot be changed after variants have been created.');
            }

            $this->variantDimensions()->sync($dimensions->mapWithKeys(
                fn (ProductAttribute $dimension, int $position): array => [$dimension->id => ['position' => $position]],
            ));

            $variants = $combinations->map(function (array $combination): ProductVariant {
                $key = self::combinationKey(collect($combination));
                $existing = $this->variants()->where('combination_key', $key)->first();

                return $existing instanceof ProductVariant
                    ? $existing->load('attributeValues.attribute')
                    : $this->createVariant($combination, ['status' => Visibility::Hidden]);
            });

            return new EloquentCollection($variants->all());
        });
    }

    /**
     * @param  array<int, ProductAttributeValue>  $attributeValues
     * @param  array<string, mixed>  $attributes
     */
    public function createVariant(array $attributeValues, array $attributes = []): ProductVariant
    {
        $values = collect($attributeValues);

        $this->assertValidVariantValues($values);

        return DB::transaction(function () use ($attributes, $values): ProductVariant {
            $this->variants()->where('is_default', true)->delete();

            /** @var ProductVariant $variant */
            $variant = $this->variants()->create(array_merge([
                'status' => Visibility::Visible,
            ], $attributes, [
                'combination_key' => self::combinationKey($values),
            ]));

            $variant->attributeValues()->attach($values->pluck('id')->all());

            return $variant->load(['product', 'attributeValues.attribute']);
        });
    }

    /**
     * @param  array<string, string>  $selection
     */
    public function variantFor(array $selection): ProductVariant
    {
        if ($selection === []) {
            throw new InvalidArgumentException('A variant selection is required.');
        }

        $dimensionIds = $this->variantDimensions()->pluck('larasell_product_attributes.id');
        $availableValues = $this->attributeValues()
            ->whereIn('product_attribute_id', $dimensionIds)
            ->with('attribute')
            ->get();
        $values = collect($selection)->map(function (string $valueSlug, string $attributeSlug) use ($availableValues): ProductAttributeValue {
            $value = $availableValues->first(fn (ProductAttributeValue $candidate): bool => $candidate->attribute->slug === $attributeSlug
                && $candidate->slug === $valueSlug);

            if (! $value instanceof ProductAttributeValue) {
                throw new InvalidArgumentException("Unknown product attribute selection [{$attributeSlug}:{$valueSlug}].");
            }

            return $value;
        });

        $requiredAttributeIds = $availableValues->pluck('product_attribute_id')->unique()->sort()->values();
        $selectedAttributeIds = $values->pluck('product_attribute_id')->unique()->sort()->values();

        if ($requiredAttributeIds->all() !== $selectedAttributeIds->all()) {
            throw new InvalidArgumentException('The variant selection is incomplete.');
        }

        /** @var ProductVariant|null $variant */
        $variant = $this->variants()
            ->where('combination_key', self::combinationKey($values))
            ->first();

        if (! $variant instanceof ProductVariant) {
            throw new InvalidArgumentException('The selected product variant does not exist.');
        }

        if ($variant->status !== Visibility::Visible) {
            throw new InvalidArgumentException('The selected product variant is unavailable.');
        }

        return $variant->load('attributeValues.attribute');
    }

    /**
     * @param  Collection<int, mixed>  $values
     */
    private function assertValidVariantValues(Collection $values): void
    {
        if ($values->isEmpty() || $values->contains(fn (mixed $value): bool => ! $value instanceof ProductAttributeValue || ! $value->exists)) {
            throw new InvalidArgumentException('Variant attribute values must be persisted product attribute values.');
        }

        if ($values->pluck('product_attribute_id')->duplicates()->isNotEmpty()) {
            throw new InvalidArgumentException('A variant cannot contain two values from the same attribute.');
        }

        $dimensionIds = $this->variantDimensions()->pluck('larasell_product_attributes.id');
        $availableValues = $this->attributeValues()
            ->whereIn('product_attribute_id', $dimensionIds)
            ->get();

        if ($values->pluck('id')->diff($availableValues->modelKeys())->isNotEmpty()) {
            throw new InvalidArgumentException('A variant attribute value is not available to this product.');
        }

        if ($dimensionIds->isEmpty()
            || $values->pluck('product_attribute_id')->unique()->sort()->values()->all() !== $dimensionIds->sort()->values()->all()) {
            throw new InvalidArgumentException('A variant attribute combination must be complete.');
        }
    }

    /**
     * @param  Collection<int, ProductAttributeValue>  $values
     */
    private static function combinationKey(Collection $values): string
    {
        return $values
            ->sortBy('product_attribute_id', SORT_NUMERIC)
            ->map(fn (ProductAttributeValue $value): string => "{$value->product_attribute_id}:{$value->id}")
            ->implode('|');
    }

    /** @return array<string, mixed> */
    private function defaultVariantAttributes(): array
    {
        return [
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'price' => $this->price,
            'stock' => $this->stock,
            'allow_backorders' => $this->allow_backorders,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            'status' => $this->status ?? Visibility::Visible,
            'is_default' => true,
            'position' => 0,
            'combination_key' => 'default',
        ];
    }

    /** @return class-string<Category> */
    protected function categoryModel(): string
    {
        return app(ModelRegistry::class)->category->class();
    }

    /** @return class-string<ProductImage> */
    protected function productImageModel(): string
    {
        return app(ModelRegistry::class)->productImage->class();
    }

    /** @return class-string<ProductAttributeValue> */
    protected function productAttributeValueModel(): string
    {
        return app(ModelRegistry::class)->productAttributeValue->class();
    }

    /** @return class-string<ProductAttribute> */
    protected function productAttributeModel(): string
    {
        return app(ModelRegistry::class)->productAttribute->class();
    }

    /** @return class-string<ProductVariant> */
    protected function productVariantModel(): string
    {
        return app(ModelRegistry::class)->productVariant->class();
    }
}
