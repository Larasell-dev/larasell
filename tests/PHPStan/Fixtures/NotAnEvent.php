<?php

namespace Larasell\Larasell\Seo\Fixtures;

use Larasell\Larasell\Models\Product;

/** Not in an Events namespace, so it may hold a model. */
final readonly class NotAnEvent
{
    public function __construct(public Product $product) {}
}
