<?php

namespace Larasell\Larasell\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Larasell\Larasell\Models\Category;
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
        return [];
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

    protected function categoryModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.category', Category::class)
            : Category::class;
    }
}
