<?php

namespace Larasell\Larasell\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Larasell\Larasell\Casts\NullableDimensionsCast;
use Larasell\Larasell\Casts\NullablePriceCast;
use Larasell\Larasell\Casts\NullableWeightCast;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Casts\TranslatableCast;
use Larasell\Larasell\Casts\TranslatableRichTextCast;
use Larasell\Larasell\Catalog\Exceptions\InvalidProductType;
use Larasell\Larasell\Catalog\Exceptions\InvalidVariantOptions;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Catalog\Options\Models\ProductOption;
use Larasell\Larasell\Catalog\Options\Models\ProductOptionValue;
use Larasell\Larasell\Catalog\ProductType;
use Larasell\Larasell\Catalog\ProductTypes;
use Larasell\Larasell\Dimensions;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\RichText\TranslatableRichText;
use Larasell\Larasell\Translatable;
use Larasell\Larasell\Weight;

/**
 * @property int $id
 * @property Translatable $name
 * @property TranslatableRichText|null $description
 * @property Price $price
 * @property Price|null $compare_at
 * @property Weight|null $weight
 * @property Dimensions|null $dimensions
 * @property Country|null $country_of_origin
 * @property string|null $hs_code Customs tariff number
 * @property string $tax_category
 * @property int|null $stock
 * @property int|null $min_quantity
 * @property int|null $max_quantity
 * @property bool $allow_backorders
 * @property Visibility $status
 * @property string $type
 *
 * @method static Builder<static> ofType(string|\BackedEnum $type)
 * @method static Builder<static> withCapability(string $capability)
 * @method static Builder<static> visible()
 * @method static Builder<static> inCategory(Category $category)
 * @method static Builder<static> inCategoryTree(Category $category)
 * @method static Builder<static> withAttributeValues()
 */
class Product extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_products';

    protected $guarded = [];

    protected $attributes = [
        'tax_category' => 'standard',
        'status' => 'visible',
        'type' => 'physical',
    ];

    protected $casts = [
        'name' => TranslatableCast::class,
        'description' => TranslatableRichTextCast::class,
        'price' => PriceCast::class,
        'compare_at' => NullablePriceCast::class,
        'weight' => NullableWeightCast::class,
        'dimensions' => NullableDimensionsCast::class,
        'country_of_origin' => Country::class,
        'stock' => 'integer',
        'allow_backorders' => 'boolean',
        'status' => Visibility::class,
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ($product->exists && $product->isDirty('type')) {
                throw InvalidProductType::immutable((string) $product->getOriginal('type'));
            }

            app(ProductTypes::class)->get($product->type);
        });

        static::created(function (Product $product): void {
            $product->variants()->create($product->defaultVariantAttributes());
        });

        static::updated(function (Product $product): void {
            if ($product->wasChanged([
                'price',
                'compare_at',
                'stock',
                'allow_backorders',
                'min_quantity',
                'max_quantity',
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

    public function productType(): ProductType
    {
        return app(ProductTypes::class)->get($this->type);
    }

    public function has(string $capability): bool
    {
        return $this->productType()->has($capability);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function ofType(Builder $query, string|\BackedEnum $type): Builder
    {
        return $query->where('type', ProductTypes::keyOf($type));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function withCapability(Builder $query, string $capability): Builder
    {
        return $query->whereIn('type', app(ProductTypes::class)->keysWith($capability));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query->where('status', Visibility::Visible->value);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
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
     * @return Builder<self>
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
     * @return Builder<self>
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
            Category::class,
            'larasell_category_product',
            'product_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'larasell_product_product_attribute_value',
            'product_id',
            'product_attribute_value_id'
        )->withTimestamps();
    }

    /** @return HasMany<ProductOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Uses the option on the product with the given values. Variants that already have option
     * values get the first of them, so none is deleted or recreated.
     *
     * @param  list<OptionDefinitionValue>  $values
     */
    public function addOption(OptionDefinition $definition, array $values): ProductOption
    {
        if ($values === [] || collect($values)->contains(fn (OptionDefinitionValue $value): bool => $value->option_definition_id !== $definition->id)) {
            throw InvalidVariantOptions::noValues();
        }

        if ($this->options()->where('option_definition_id', $definition->id)->exists()) {
            throw InvalidVariantOptions::alreadyUsed($definition->slug);
        }

        return DB::transaction(function () use ($definition, $values): ProductOption {
            /** @var ProductOption $option */
            $option = $this->options()->create([
                'option_definition_id' => $definition->id,
                'position' => (int) $this->options()->max('position') + ($this->options()->exists() ? 1 : 0),
            ]);

            foreach ($values as $position => $value) {
                $option->values()->create(['option_definition_value_id' => $value->id, 'position' => $position]);
            }

            $first = $option->values()->firstOrFail();

            $this->variants()->whereHas('optionValues')->get()->each(function (ProductVariant $variant) use ($option, $first): void {
                $variant->optionValues()->attach($first->id, ['product_option_id' => $option->id]);
                $variant->update(['combination_key' => self::optionKey($variant->optionValues()->get())]);
            });

            return $option;
        });
    }

    /** Offers one more value of the definition for the product's option. */
    public function addOptionValue(OptionDefinition $definition, OptionDefinitionValue $value): ProductOptionValue
    {
        $option = $this->options()->where('option_definition_id', $definition->id)->firstOrFail();

        if ($value->option_definition_id !== $definition->id) {
            throw InvalidVariantOptions::notOffered($value->slug);
        }

        /** @var ProductOptionValue */
        return $option->values()->firstOrCreate(
            ['option_definition_value_id' => $value->id],
            ['position' => (int) $option->values()->max('position') + 1],
        );
    }

    /**
     * Creates a variant for each given combination, one value of every option each. The default
     * variant becomes the first combination while it has none, so its ID stays.
     *
     * @param  list<list<OptionDefinitionValue>>  $combinations
     * @return list<ProductVariant>
     */
    public function createCombinations(array $combinations): array
    {
        return DB::transaction(function () use ($combinations): array {
            $options = $this->options()->with('values')->get();
            $offered = $options->flatMap->values->keyBy('option_definition_value_id');
            $keys = $this->variants()->pluck('combination_key')->all();
            $created = [];

            foreach ($combinations as $combination) {
                $values = collect($combination)->map(function (OptionDefinitionValue $value) use ($offered): ProductOptionValue {
                    return $offered->get($value->id) ?? throw InvalidVariantOptions::notOffered($value->slug);
                });

                if ($options->isEmpty()
                    || $values->pluck('product_option_id')->unique()->count() !== $values->count()
                    || $values->count() !== $options->count()) {
                    throw InvalidVariantOptions::incomplete();
                }

                $key = self::optionKey($values);

                if (in_array($key, $keys, true)) {
                    throw InvalidVariantOptions::duplicate();
                }

                $keys[] = $key;
                $variant = $this->variants()->where('is_default', true)->whereDoesntHave('optionValues')->first()
                    ?? $this->variants()->create(['status' => Visibility::Hidden, 'position' => count($keys) - 1, 'combination_key' => $key]);
                $variant->update(['combination_key' => $key]);
                $variant->optionValues()->attach($values->mapWithKeys(
                    fn (ProductOptionValue $value): array => [$value->id => ['product_option_id' => $value->product_option_id]],
                )->all());
                $created[] = $variant->fresh() ?? $variant;
            }

            return $created;
        });
    }

    /** @param  Collection<array-key, ProductOptionValue>|EloquentCollection<array-key, ProductOptionValue>  $values */
    private static function optionKey(Collection|EloquentCollection $values): string
    {
        return 'o:'.collect($values)
            ->sortBy('product_option_id', SORT_NUMERIC)
            ->map(fn (ProductOptionValue $value): string => "{$value->product_option_id}:{$value->id}")
            ->implode('|');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function visibleVariants(): HasMany
    {
        return $this->variants()
            ->chaperone()
            ->visible()
            ->with(['optionValues.option.definition', 'optionValues.definitionValue'])
            ->orderBy('position')
            ->orderBy('id');
    }

    public function onSale(): bool
    {
        return $this->compare_at !== null && $this->compare_at->greaterThan($this->price);
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

    /** @return array<string, mixed> */
    private function defaultVariantAttributes(): array
    {
        return [
            'price' => $this->price,
            'compare_at' => $this->compare_at,
            'stock' => $this->stock,
            'allow_backorders' => $this->allow_backorders,
            'min_quantity' => $this->min_quantity,
            'max_quantity' => $this->max_quantity,
            // Visible on its own: the product's status already hides it, as it hides every variant.
            'status' => Visibility::Visible,
            'is_default' => true,
            'position' => 0,
            'combination_key' => 'default',
        ];
    }
}
