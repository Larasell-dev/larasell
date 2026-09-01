<?php

namespace Larasell\Larasell\Http\Requests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Rule;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Product;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductListingRequest extends FormRequest
{
    private ?Category $resolvedCategory = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc'])],
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function category(): Category
    {
        if ($this->resolvedCategory !== null) {
            return $this->resolvedCategory;
        }

        $slug = collect(explode('/', trim((string) $this->route('category'), '/')))
            ->filter()
            ->last();

        if (! $slug) {
            throw new NotFoundHttpException;
        }

        return $this->resolvedCategory = app(ModelRegistry::class)->category->query()
            ->where('slug->'.App::currentLocale(), $slug)
            ->firstOrFail();
    }

    /** @return Builder<Product> */
    public function products(): Builder
    {
        $category = $this->category();

        $products = app(ModelRegistry::class)->product->query()
            ->visible()
            ->inCategoryTree($category);

        foreach ($this->attributeFilters() as $attributeSlug => $valueSlugs) {
            $products->whereHas(
                'attributeValues',
                fn (Builder $query) => $query
                    ->whereIn($query->getModel()->qualifyColumn('slug'), $valueSlugs)
                    ->whereHas(
                        'attribute',
                        fn (Builder $query) => $query->where($query->getModel()->qualifyColumn('slug'), $attributeSlug),
                    ),
            );
        }

        return match ($this->sort()) {
            'name' => $products->orderByRaw($this->translatedNameExpression($products).' asc'),
            'price_asc' => $products->orderByRaw($this->priceAmountExpression($products).' asc'),
            'price_desc' => $products->orderByRaw($this->priceAmountExpression($products).' desc'),
            default => $products,
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function attributeFilters(): array
    {
        return collect($this->query('attributes', []))
            ->map(fn (mixed $values): array => is_array($values) ? $values : [$values])
            ->map(fn (array $values): array => collect($values)
                ->filter(fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '')
                ->map(fn (mixed $value): string => (string) $value)
                ->values()
                ->all())
            ->filter(fn (array $values, mixed $attribute): bool => is_string($attribute) && $attribute !== '' && $values !== [])
            ->all();
    }

    public function sort(): string
    {
        return match ($this->query('sort')) {
            'price_asc' => 'price_asc',
            'price_desc' => 'price_desc',
            default => 'name',
        };
    }

    /** @param Builder<Product> $query */
    private function priceAmountExpression(Builder $query): string
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $column = $query->getModel()->qualifyColumn('price');
        $wrappedColumn = $query->getModel()->getConnection()->getQueryGrammar()->wrap($column);

        return match ($driver) {
            'mysql', 'mariadb' => "CAST(JSON_UNQUOTE(JSON_EXTRACT({$wrappedColumn}, '$.amount')) AS UNSIGNED)",
            'pgsql' => "CAST({$wrappedColumn}->>'amount' AS BIGINT)",
            'sqlsrv' => "CAST(JSON_VALUE({$wrappedColumn}, '$.amount') AS BIGINT)",
            default => "CAST(json_extract({$wrappedColumn}, '$.amount') AS INTEGER)",
        };
    }

    /** @param Builder<Product> $query */
    private function translatedNameExpression(Builder $query): string
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $column = $query->getModel()->qualifyColumn('name');
        $wrappedColumn = $query->getModel()->getConnection()->getQueryGrammar()->wrap($column);
        $locale = str_replace("'", "''", App::currentLocale());

        return match ($driver) {
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT({$wrappedColumn}, '$.\"{$locale}\"'))",
            'pgsql' => "{$wrappedColumn}->>'{$locale}'",
            'sqlsrv' => "JSON_VALUE({$wrappedColumn}, '$.\"{$locale}\"')",
            default => "json_extract({$wrappedColumn}, '$.\"{$locale}\"')",
        };
    }
}
