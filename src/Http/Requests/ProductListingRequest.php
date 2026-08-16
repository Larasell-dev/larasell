<?php

namespace Larasell\Larasell\Http\Requests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductListingRequest extends FormRequest
{
    private ?Model $resolvedCategory = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => ['nullable', Rule::in(['price_asc', 'price_desc'])],
        ];
    }

    public function category(): Model
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

        return $this->resolvedCategory = $this->categoryModel()::query()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    public function products(): Builder
    {
        $category = $this->category();
        $categoryIds = $category->descendants()
            ->pluck('id')
            ->push($category->id);

        $products = $this->productModel()::query()
            ->visible()
            ->whereHas(
                'categories',
                fn (Builder $query) => $query->whereIn($query->getModel()->qualifyColumn('id'), $categoryIds),
            );

        return match ($this->sort()) {
            'name' => $products->orderBy('name'),
            'price_asc' => $products->orderByRaw($this->priceAmountExpression($products).' asc'),
            'price_desc' => $products->orderByRaw($this->priceAmountExpression($products).' desc'),
            default => $products,
        };
    }

    public function sort(): string
    {
        return match ($this->query('sort')) {
            'price_asc' => 'price_asc',
            'price_desc' => 'price_desc',
            default => 'name',
        };
    }

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

    protected function categoryModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.category', Category::class)
            : Category::class;
    }

    protected function productModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.product', Product::class)
            : Product::class;
    }
}
