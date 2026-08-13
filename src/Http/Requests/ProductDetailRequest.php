<?php

namespace Larasell\Larasell\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Larasell\Larasell\Models\Product;

class ProductDetailRequest extends FormRequest
{
    private ?Model $resolvedProduct = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function product(): Model
    {
        if ($this->resolvedProduct !== null) {
            return $this->resolvedProduct;
        }

        $product = $this->route('product');

        if ($product instanceof Model) {
            return $this->resolvedProduct = $product;
        }

        return $this->resolvedProduct = $this->productModel()::query()
            ->where('slug', $product)
            ->firstOrFail();
    }

    protected function productModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.product', Product::class)
            : Product::class;
    }
}
