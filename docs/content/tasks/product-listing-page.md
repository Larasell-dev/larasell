---
title: Product Listing Page
description: Create a category-based product listing page.
---

You'll learn how to create a product listing page, so that customers
are able to browse through the products the store has to offer.

We can achieve this by in a couple of handful steps:

1. Define the route
2. Create a controller
3. Render a template

## Defining the route

First up, we need to define under which URL we can browse products.

```php
// routes/web.php
<?php

use App\Http\Controllers\ProductController;
use Larasell\Larasell\Routing\ProductListingRoute;

ProductListingRoute::get(ProductController::index, prefix: 'c')
```

It's required that you define a prefix for this route. In this case
if you have category `Mens -> Clothing -> Shirt` the route will be
`/c/mens/clothing/shirt`.

## Creating a controller

Next up, we'll actually fetch the products

```php
// ProductController.php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Http\Requests\ProductListingRequest;

class ProductController
{
    public function index(ProductListingRequest $request): Response
    {
       $category = $request->category();
       $products = $request->products()->get();

       return Inertia::render('Products/Index', [
           'category' => $category,
           'products' => $products
       ]);
    }
}
```

The request supports sorting and product attribute filters through query
parameters:

```text
/c/mens/clothing/shirts?sort=price_asc&attributes[size][]=small&attributes[size][]=medium&attributes[color]=black
```

Multiple values for the same attribute match any selected value. Multiple
attributes must all match.

## Creating a template

Finally, create an Inertia page that receives the category and products
from the controller.

```jsx
// resources/js/Pages/Products/Index.jsx
export default function Index({ category, products }) {
    return (
        <main>
            <h1>{category.name}</h1>

            <ul>
                {products.map((product) => (
                    <li key={product.id}>
                        <a href={`/p/${product.slug}`}>
                            <h2>{product.name}</h2>
                            <p>{product.price}</p>
                        </a>
                    </li>
                ))}
            </ul>
        </main>
    );
}
```
