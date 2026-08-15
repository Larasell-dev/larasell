<?php

namespace Larasell\Larasell\Routing;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Models\Category;

class CategoryUrl
{
    public static function make(Model $category, string $prefix = 'c'): string
    {
        return '/'.trim($prefix, '/').'/'.(new self)->path($category);
    }

    private function path(Model $category): string
    {
        return collect($this->segments($category))->implode('/');
    }

    /**
     * @return array<int, string>
     */
    private function segments(Model $category): array
    {
        $segments = [];

        while ($category) {
            array_unshift($segments, $category->slug);

            $category = $category->relationLoaded('parent')
                ? $category->parent
                : $this->parent($category);
        }

        return $segments;
    }

    private function parent(Model $category): ?Model
    {
        if (! $category->parent_id) {
            return null;
        }

        return $this->categoryModel()::query()->find($category->parent_id);
    }

    /**
     * @return class-string<Category>
     */
    private function categoryModel(): string
    {
        return app()->bound('config')
            ? config('larasell.models.category', Category::class)
            : Category::class;
    }
}
