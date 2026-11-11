<?php

namespace Larasell\Larasell\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Casts\NullableDimensionsCast;
use Larasell\Larasell\Casts\NullablePriceCast;
use Larasell\Larasell\Casts\NullableWeightCast;
use Larasell\Larasell\Catalog\AttributeCardinality;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Catalog\Exceptions\InvalidAttributeValues;
use Larasell\Larasell\Catalog\Exceptions\InvalidInheritance;
use Larasell\Larasell\Catalog\Exceptions\InvalidProductType;
use Larasell\Larasell\Catalog\InheritanceState;
use Larasell\Larasell\Catalog\Options\Models\ProductOptionValue;
use Larasell\Larasell\Catalog\ProductType;
use Larasell\Larasell\Catalog\ProductTypes;
use Larasell\Larasell\Dimensions;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Price;
use Larasell\Larasell\RichText\RichText;
use Larasell\Larasell\RichText\TranslatableRichText;
use Larasell\Larasell\Settings\LocaleSettings;
use Larasell\Larasell\Translatable;
use Larasell\Larasell\Weight;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $sku
 * @property string|null $barcode
 * @property string|null $mpn Manufacturer part number, not unique
 * @property array<string, string>|null $name Own names by locale; a missing locale inherits
 * @property array<string, string|null>|null $description Own descriptions by locale as rich text JSON; null clears, missing inherits
 * @property Price|null $price
 * @property Price|null $compare_at
 * @property Weight|null $weight Only when overridden
 * @property InheritanceState $weight_inheritance
 * @property Dimensions|null $dimensions Only when overridden
 * @property InheritanceState $dimensions_inheritance
 * @property Country|null $country_of_origin Only when overridden
 * @property InheritanceState $country_of_origin_inheritance
 * @property string|null $hs_code Customs tariff number, only when overridden
 * @property InheritanceState $hs_code_inheritance
 * @property string|null $tax_category
 * @property int|null $stock
 * @property bool|null $allow_backorders
 * @property int|null $min_quantity
 * @property int|null $max_quantity
 * @property Visibility $status
 * @property bool $is_default
 * @property string|null $type Only when overridden
 * @property InheritanceState $type_inheritance
 * @property int $position
 * @property string $combination_key
 * @property array<string, mixed>|null $metadata
 * @property Product $product
 * @property Collection<int, ProductAttributeValue> $attributeValues
 *
 * @method static Builder<static> visible()
 */
class ProductVariant extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** The fields a variant can inherit from its product, override or clear. */
    public const array INHERITABLE = ['weight', 'dimensions', 'country_of_origin', 'hs_code', 'type'];

    /** The translatable fields a variant can inherit, override or clear per locale. */
    public const array TRANSLATED = ['name', 'description'];

    /** Fields every variant has a value of, so an override can't be empty. */
    private const array REQUIRED = ['type', 'name'];

    protected $table = 'larasell_product_variants';

    protected $attributes = [
        'weight_inheritance' => 'inherited',
        'dimensions_inheritance' => 'inherited',
        'country_of_origin_inheritance' => 'inherited',
        'hs_code_inheritance' => 'inherited',
        'type_inheritance' => 'inherited',
    ];

    protected $guarded = [];

    protected $casts = [
        'price' => NullablePriceCast::class,
        'compare_at' => NullablePriceCast::class,
        'weight' => NullableWeightCast::class,
        'dimensions' => NullableDimensionsCast::class,
        'country_of_origin' => Country::class,
        'weight_inheritance' => InheritanceState::class,
        'dimensions_inheritance' => InheritanceState::class,
        'country_of_origin_inheritance' => InheritanceState::class,
        'hs_code_inheritance' => InheritanceState::class,
        'type_inheritance' => InheritanceState::class,
        'name' => 'array',
        'description' => 'array',
        'stock' => 'integer',
        'allow_backorders' => 'boolean',
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'status' => Visibility::class,
        'is_default' => 'boolean',
        'position' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductVariant $variant): void {
            if ($variant->exists && $variant->isDirty('type')) {
                throw InvalidProductType::immutable((string) ($variant->getOriginal('type') ?? 'inherited'));
            }

            foreach (self::INHERITABLE as $field) {
                $state = $variant->inheritance($field);

                if ($state === InheritanceState::Overridden && $variant->{$field} === null && in_array($field, self::REQUIRED, true)) {
                    throw InvalidInheritance::overrideWithoutValue($field);
                }

                if ($state === InheritanceState::Inherited && $variant->{$field} !== null) {
                    throw InvalidInheritance::valueWithoutOverride($field);
                }
            }

            if ($variant->type !== null) {
                app(ProductTypes::class)->get($variant->type);
            }
        });
    }

    public function inheritance(string $field): InheritanceState
    {
        self::assertInheritable($field, self::INHERITABLE);

        return $this->{"{$field}_inheritance"};
    }

    /** Uses the variant's own value of the field; null gives it none, whatever the product has. */
    public function override(string $field, mixed $value): static
    {
        self::assertInheritable($field, self::INHERITABLE);
        $this->{"{$field}_inheritance"} = InheritanceState::Overridden;
        $this->{$field} = $value;

        return $this;
    }

    /** Uses the product's value of the field. */
    public function inherit(string $field): static
    {
        self::assertInheritable($field, self::INHERITABLE);
        $this->{"{$field}_inheritance"} = InheritanceState::Inherited;
        $this->{$field} = null;

        return $this;
    }

    public function translationInheritance(string $field, string $locale): InheritanceState
    {
        self::assertInheritable($field, self::TRANSLATED);
        $own = $this->{$field} ?? [];
        $locale = Locale::canonicalize($locale);

        return array_key_exists($locale, $own) ? InheritanceState::Overridden : InheritanceState::Inherited;
    }

    /** The variant's own text in the locale; null gives it none there, whatever the product has. */
    public function overrideTranslation(string $field, string $locale, string|RichText|null $value): static
    {
        if ($value === null && in_array($field, self::REQUIRED, true)) {
            throw InvalidInheritance::overrideWithoutValue($field);
        }

        return $this->withTranslation($field, $locale, $value instanceof RichText ? $value->toJson() : $value);
    }

    public function inheritTranslation(string $field, string $locale): static
    {
        self::assertInheritable($field, self::TRANSLATED);
        $own = $this->{$field} ?? [];
        unset($own[Locale::canonicalize($locale)]);
        $this->{$field} = $own === [] ? null : $own;

        return $this;
    }

    private function withTranslation(string $field, string $locale, ?string $value): static
    {
        self::assertInheritable($field, self::TRANSLATED);
        $this->{$field} = [...($this->{$field} ?? []), Locale::canonicalize($locale) => $value];

        return $this;
    }

    /**
     * Walks the locale's lookup chain; at each locale the variant's own value, empty or not, wins
     * over the product's, so the product in a more specific locale wins over the variant in a
     * broader one.
     *
     * @return array{0: bool, 1: mixed} Whether the value is the variant's own, and the value
     */
    private function resolveTranslation(string $field, ?string $locale, Translatable|TranslatableRichText|null $product): array
    {
        $own = $this->{$field} ?? [];
        $locale = Locale::canonicalize($locale ?? app()->getLocale());
        $chain = array_unique([
            ...Locale::lookupChain($locale),
            ...Locale::lookupChain(app(LocaleSettings::class)->default()->value),
        ]);

        foreach ($chain as $candidate) {
            if (array_key_exists($candidate, $own)) {
                return [true, $own[$candidate]];
            }

            if ($product?->has($candidate)) {
                return [false, $product->get($candidate)];
            }
        }

        return [false, $product?->get($locale)];
    }

    /** @param  list<string>  $fields */
    private static function assertInheritable(string $field, array $fields): void
    {
        if (! in_array($field, $fields, true)) {
            throw InvalidInheritance::notInheritable($field);
        }
    }

    /** The field's value as the variant uses it: its own, the product's or none. */
    private function effective(string $field): mixed
    {
        return match ($this->inheritance($field)) {
            InheritanceState::Overridden => $this->{$field},
            InheritanceState::Inherited => $this->product->{$field},
        };
    }

    public function effectiveType(): ProductType
    {
        return app(ProductTypes::class)->get($this->effective('type'));
    }

    public function has(string $capability): bool
    {
        return $this->effectiveType()->has($capability);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function visible(Builder $query): Builder
    {
        return $query
            ->where('status', Visibility::Visible->value)
            ->whereHas('product', fn (Builder $product) => $product->where('status', Visibility::Visible->value));
    }

    /** Visible in the store: only while both the variant and its product are visible. */
    public function isVisible(): bool
    {
        return $this->status === Visibility::Visible
            && $this->product->status === Visibility::Visible;
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    private const string ATTRIBUTE_TABLE = 'larasell_product_variant_attribute_values';

    public function attributeInheritance(ProductAttribute $attribute): InheritanceState
    {
        $state = DB::table(self::ATTRIBUTE_TABLE)
            ->where('product_variant_id', $this->id)
            ->where('product_attribute_id', $attribute->id)
            ->value('state');

        return $state === null ? InheritanceState::Inherited : InheritanceState::from($state);
    }

    /**
     * Gives the variant its own values of the attribute, which replace the product's whole list;
     * none gives it none, whatever the product has. Single-value attributes take one at most.
     */
    public function overrideAttribute(ProductAttribute $attribute, ProductAttributeValue ...$values): void
    {
        if ($attribute->scope === AttributeScope::Product) {
            throw InvalidInheritance::notInheritable($attribute->slug);
        }

        foreach ($values as $value) {
            if ($value->product_attribute_id !== $attribute->id) {
                throw InvalidAttributeValues::ofAnotherAttribute($attribute->slug);
            }
        }

        $ids = array_values(array_unique(array_map(fn (ProductAttributeValue $value): int => $value->id, $values)));

        if (count($ids) > 1 && $attribute->cardinality === AttributeCardinality::Single) {
            throw InvalidAttributeValues::tooMany($attribute->slug);
        }

        DB::transaction(function () use ($attribute, $ids): void {
            $this->inheritAttribute($attribute);
            DB::table(self::ATTRIBUTE_TABLE)->insert(array_map(fn (?int $id): array => [
                'product_variant_id' => $this->id,
                'product_attribute_id' => $attribute->id,
                'product_attribute_value_id' => $id,
                'state' => InheritanceState::Overridden->value,
            ], $ids === [] ? [null] : $ids));
        });
    }

    /** Uses the product's values of the attribute again. */
    public function inheritAttribute(ProductAttribute $attribute): void
    {
        DB::table(self::ATTRIBUTE_TABLE)
            ->where('product_variant_id', $this->id)
            ->where('product_attribute_id', $attribute->id)
            ->delete();
    }

    /**
     * The attribute values that describe the variant: its own, and the product's values of
     * inherited attributes it doesn't override.
     *
     * @return Collection<int, ProductAttributeValue>
     */
    public function effectiveAttributeValues(): Collection
    {
        $rows = DB::table(self::ATTRIBUTE_TABLE)->where('product_variant_id', $this->id)->get();
        $decided = $rows->pluck('product_attribute_id')->map(fn (mixed $id): int => (int) $id)->all();
        $own = ProductAttributeValue::query()->with('attribute')
            ->whereKey($rows->pluck('product_attribute_value_id')->filter()->all())->get();
        $inherited = $this->product->attributeValues()->with('attribute')->get()->filter(
            fn (ProductAttributeValue $value): bool => $value->attribute->scope === AttributeScope::Inherited
                && ! in_array($value->product_attribute_id, $decided, true),
        );

        return new Collection($inherited->concat($own)->values()->all());
    }

    /** @return BelongsToMany<ProductOptionValue, $this> */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'larasell_product_variant_option_values',
            'product_variant_id',
            'product_option_value_id',
        )->withPivot('product_option_id');
    }

    /** @return BelongsToMany<ProductAttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductAttributeValue::class,
            'larasell_product_variant_product_attribute_value',
            'product_variant_id',
            'product_attribute_value_id',
        );
    }

    public function unitPrice(): Price
    {
        return $this->price ?? $this->product->price;
    }

    public function compareAtPrice(): ?Price
    {
        return $this->compare_at ?? $this->product->compare_at;
    }

    public function onSale(): bool
    {
        $compareAt = $this->compareAtPrice();

        return $compareAt !== null && $compareAt->greaterThan($this->unitPrice());
    }

    public function effectiveTaxCategory(): string
    {
        return $this->tax_category ?? $this->product->tax_category;
    }

    public function effectiveWeight(): ?Weight
    {
        return $this->effective('weight');
    }

    public function effectiveDimensions(): ?Dimensions
    {
        return $this->effective('dimensions');
    }

    public function effectiveCountryOfOrigin(): ?Country
    {
        return $this->effective('country_of_origin');
    }

    public function effectiveHsCode(): ?string
    {
        return $this->effective('hs_code');
    }

    public function availableStock(): ?int
    {
        return $this->stock ?? $this->product->stock;
    }

    public function allowsBackorders(): bool
    {
        return $this->allow_backorders ?? $this->product->allow_backorders;
    }

    public function minimumQuantity(): ?int
    {
        return $this->min_quantity ?? $this->product->min_quantity;
    }

    public function maximumQuantity(): ?int
    {
        return $this->max_quantity ?? $this->product->max_quantity;
    }

    /**
     * @return array<string, array{attribute_id: int, attribute_slug: string, attribute_name: string, value_id: int, value_slug: string, value_name: string}>
     */
    public function options(?string $locale = null): array
    {
        $this->loadMissing('optionValues.option.definition', 'optionValues.definitionValue');

        if ($this->optionValues->isNotEmpty()) {
            return $this->optionValues
                ->sortBy(fn (ProductOptionValue $value): array => [$value->option->position, $value->option->id])
                ->mapWithKeys(fn (ProductOptionValue $value): array => [
                    $value->option->definition->slug => [
                        'attribute_id' => $value->option->definition->id,
                        'attribute_slug' => $value->option->definition->slug,
                        'attribute_name' => $value->option->definition->name->get($locale),
                        'value_id' => $value->definitionValue->id,
                        'value_slug' => $value->definitionValue->slug,
                        'value_name' => $value->definitionValue->name->get($locale),
                    ],
                ])
                ->all();
        }

        if ($this->is_default) {
            return [];
        }

        $this->loadMissing(['product.variantDimensions', 'attributeValues.attribute']);

        $positions = $this->product->variantDimensions
            ->pluck('pivot.position', 'id');

        return $this->attributeValues
            ->sortBy(fn (ProductAttributeValue $value): array => [
                $positions->get($value->product_attribute_id, PHP_INT_MAX),
                $value->product_attribute_id,
            ])
            ->mapWithKeys(fn (ProductAttributeValue $value): array => [
                $value->attribute->slug => [
                    'attribute_id' => $value->attribute->id,
                    'attribute_slug' => $value->attribute->slug,
                    'attribute_name' => $value->attribute->name->get($locale),
                    'value_id' => $value->id,
                    'value_slug' => $value->slug,
                    'value_name' => $value->name->get($locale),
                ],
            ])
            ->all();
    }

    /** The variant's own name in the locale, or else its option values, or else the product's name. */
    public function name(?string $locale = null): string
    {
        [$own, $name] = $this->resolveTranslation('name', $locale, $this->product->name);

        if ($own) {
            return $name;
        }

        $options = $this->options($locale);

        return $options === [] ? $name : collect($options)->pluck('value_name')->implode(' / ');
    }

    /** The variant's own description in the locale, the product's, or none when its own is empty. */
    public function description(?string $locale = null): ?RichText
    {
        [$own, $description] = $this->resolveTranslation('description', $locale, $this->product->description);

        return $own && $description !== null ? RichText::fromJson($description) : $description;
    }

    public function decrementInventory(int $quantity): void
    {
        if ($this->stock === null) {
            $this->product->decrement('stock', $quantity);

            return;
        }

        $this->decrement('stock', $quantity);

        if ($this->is_default) {
            $this->product->decrement('stock', $quantity);
        }
    }

    public function incrementInventory(int $quantity): void
    {
        if ($this->stock === null) {
            $this->product->increment('stock', $quantity);

            return;
        }

        $this->increment('stock', $quantity);

        if ($this->is_default) {
            $this->product->increment('stock', $quantity);
        }
    }
}
