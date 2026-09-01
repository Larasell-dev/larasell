<?php

namespace Larasell\Larasell\Routing;

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\ModelRegistry;

class CategoryUrl
{
    public static function make(Category $category, string $prefix = 'c'): string
    {
        return '/'.trim($prefix, '/').'/'.(new self)->path($category);
    }

    private function path(Category $category): string
    {
        return collect($this->segments($category))->implode('/');
    }

    /**
     * @return array<int, string>
     */
    private function segments(Category $category): array
    {
        $segments = [];

        while ($category) {
            array_unshift($segments, $category->slug->get());

            $category = $category->relationLoaded('parent')
                ? $category->parent
                : $this->parent($category);
        }

        return $segments;
    }

    private function parent(Category $category): ?Category
    {
        if (! $category->parent_id) {
            return null;
        }

        return app(ModelRegistry::class)->category->query()->find($category->parent_id);
    }
}
