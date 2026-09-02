<?php

namespace App\Inertia;

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Routing\CategoryUrl;

final class NavigationProp implements Propable
{
    /**
     * @return array<int, array{name: string, url: string, children: array<int, mixed>}>
     */
    public function prop(): array
    {
        return Category::query()
            ->root()
            ->with('descendants')
            ->get()
            ->map(fn (Category $category): array => $this->navigationItem($category))
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, url: string, children: array<int, mixed>}
     */
    private function navigationItem(Category $category): array
    {
        return [
            'name' => $category->name->get(),
            'url' => CategoryUrl::make($category, prefix: 'c'),
            'children' => $category->descendants
                ->map(fn (Category $category): array => $this->navigationItem($category))
                ->values()
                ->all(),
        ];
    }
}
