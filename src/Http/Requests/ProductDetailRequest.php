<?php

namespace Larasell\Larasell\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Product;

class ProductDetailRequest extends FormRequest
{
    private ?Product $resolvedProduct = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function product(): Product
    {
        if ($this->resolvedProduct !== null) {
            return $this->resolvedProduct;
        }

        $product = $this->route('product');

        if ($product instanceof Product) {
            return $this->resolvedProduct = $product;
        }

        return $this->resolvedProduct = app(ModelRegistry::class)->product->query()
            ->where('slug->'.App::currentLocale(), $product)
            ->firstOrFail();
    }
}
