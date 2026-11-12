<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Exceptions\InvalidVariantOptions;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinition;
use Larasell\Larasell\Catalog\Options\Models\OptionDefinitionValue;
use Larasell\Larasell\Catalog\Options\Models\ProductOptionValue;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

/**
 * Gives a product exactly the given options and values, in one transaction. New options give
 * existing variants their first value; removed values delete the variants that use them, and a
 * removed option keeps only the variants of its first value. The product keeps one variant.
 */
final readonly class SyncProductOptions
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  array<int, list<int>>  $selection  Value IDs by option definition ID, in order
     *
     * @throws InvalidVariantOptions
     */
    public function handle(ProductId $productId, array $selection): void
    {
        $this->db->transaction(function () use ($productId, $selection): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $values = $this->validated($selection);

            foreach ($this->doomed($product, $selection) as $variant) {
                $variant->delete();
                ProductVariantDeleted::dispatch(VariantId::of($variant), $productId);
            }

            foreach ($product->options()->with('values')->get() as $option) {
                $kept = $selection[$option->option_definition_id] ?? null;

                if ($kept === null) {
                    // The variants left all have its first value, so dropping it keeps them apart.
                    // Their rows go first: MySQL won't cascade to values those rows still use.
                    $this->db->table('larasell_product_variant_option_values')->where('product_option_id', $option->id)->delete();
                    $option->delete();
                } else {
                    $option->values->reject(fn (ProductOptionValue $value): bool => in_array($value->option_definition_value_id, $kept, true))->each->delete();
                }
            }

            foreach ($selection as $definitionId => $ids) {
                $definition = OptionDefinition::query()->findOrFail($definitionId);
                $chosen = array_map(fn (int $id): OptionDefinitionValue => $values->get($id) ?? throw InvalidVariantOptions::notOffered((string) $id), $ids);

                if ($product->options()->where('option_definition_id', $definitionId)->exists()) {
                    foreach ($chosen as $value) {
                        $product->addOptionValue($definition, $value);
                    }
                } else {
                    $product->addOption($definition, $chosen);
                }
            }

            $this->rekey($product);
        });
    }

    /**
     * How many variants handle() would delete.
     *
     * @param  array<int, list<int>>  $selection
     */
    public function deletions(ProductId $productId, array $selection): int
    {
        return $this->doomed(Product::query()->findOrFail($productId->value), $selection)->count();
    }

    /**
     * @param  array<int, list<int>>  $selection
     * @return Collection<int, OptionDefinitionValue>
     */
    private function validated(array $selection): Collection
    {
        $values = OptionDefinitionValue::query()->whereKey(array_merge([], ...array_values($selection)))->get()->keyBy('id');

        foreach ($selection as $definitionId => $ids) {
            if ($ids === []) {
                throw InvalidVariantOptions::noValues();
            }

            foreach ($ids as $id) {
                if ($values->get($id)?->option_definition_id !== $definitionId) {
                    throw InvalidVariantOptions::notOffered((string) $id);
                }
            }
        }

        return $values;
    }

    /**
     * The variants that use a removed value, or another than the first of a removed option.
     *
     * @param  array<int, list<int>>  $selection
     * @return Collection<int, ProductVariant>
     */
    private function doomed(Product $product, array $selection): Collection
    {
        $gone = [];

        foreach ($product->options()->with('values')->get() as $option) {
            $kept = $selection[$option->option_definition_id] ?? null;
            $values = $kept === null
                ? $option->values->slice(1)
                : $option->values->reject(fn (ProductOptionValue $value): bool => in_array($value->option_definition_value_id, $kept, true));
            array_push($gone, ...$values->modelKeys());
        }

        $doomed = $product->variants()->whereHas('optionValues', fn ($query) => $query->whereKey($gone))->get();

        if ($doomed->count() === $product->variants()->count()) {
            throw InvalidVariantOptions::wouldDeleteEveryVariant();
        }

        return $doomed;
    }

    /** Keys the variants by their values again, and keeps one of them the default. */
    private function rekey(Product $product): void
    {
        $variants = $product->variants()->with('optionValues')->orderBy('position')->orderBy('id')->get();

        foreach ($variants as $variant) {
            $variant->update(['combination_key' => $variant->optionValues->isEmpty() ? 'default' : 'o:'.$variant->optionValues
                ->sortBy('product_option_id', SORT_NUMERIC)
                ->map(fn (ProductOptionValue $value): string => "{$value->product_option_id}:{$value->id}")
                ->implode('|')]);
        }

        if (! $variants->contains('is_default', true)) {
            $variants->first()?->update(['is_default' => true]);
        }
    }
}
