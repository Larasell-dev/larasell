<?php

namespace Larasell\Larasell\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Larasell\Larasell\Models\ModelRegistry;

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

        return $this->resolvedProduct = app(ModelRegistry::class)->product->query()
            ->where('slug->'.App::currentLocale(), $product)
            ->firstOrFail();
    }
}
