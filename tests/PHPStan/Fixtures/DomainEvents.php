<?php

namespace Larasell\Larasell\Catalog\Events;

use Illuminate\Database\Eloquent\Collection;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Translatable;

final readonly class CarriesModels
{
    /** @param  Collection<int, Category>  $categories */
    public function __construct(
        public ?Product $product,
        public Collection $categories,
        public int $productId,
    ) {}
}

final readonly class CarriesFacts
{
    /** @param  list<int>  $categoryIds */
    public function __construct(
        public int $productId,
        public array $categoryIds,
        public Translatable $name,
        public ?Locale $locale,
    ) {}
}
