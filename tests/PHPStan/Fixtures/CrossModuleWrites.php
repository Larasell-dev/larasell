<?php

namespace Larasell\Larasell\Seo\Fixtures;

use Larasell\Larasell\Models\Category;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Seo\Models\SeoUrl;

final class CrossModuleWrites
{
    public function writes(Product $product, Category $category): void
    {
        $product->update(['name' => ['en' => 'Boots']]);
        $product->save();
        Product::create(['name' => ['en' => 'Boots']]);
        Product::query()->whereKey(1)->update(['status' => 'hidden']);
        $category->children()->create(['name' => ['en' => 'Boots']]);
        $product->categories()->attach($category);
    }

    public function reads(Product $product): void
    {
        // Reads of another module's models are fine, and so are writes to the module's own models.
        $product->name->get('en');
        Product::query()->whereKey(1)->first();
        $product->categories()->get();
        $product->fill(['name' => ['en' => 'Boots']]);
        SeoUrl::query()->where('path', 'p/boots')->delete();
        (new SeoUrl)->save();
    }
}
