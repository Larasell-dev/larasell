You'll learn how to create a product detail page, so that customers
are able to view a single product before adding it to the cart.

We can achieve this by in a couple of handful steps:

1. Define the route
2. Create a controller
3. Render a template

## Defining the route

First up, we need to define under which URL we can view a product.

```php
// routes/web.php
<?php

use App\Http\Controllers\ProductController;
use Larasell\Larasell\Routing\ProductDetailRoute;

ProductDetailRoute::get(ProductController::show, prefix: 'p')
```

It's required that you define a prefix for this route. In this case
if you have product `Classic T-Shirt` the route will be
`/p/classic-t-shirt`.

## Creating a controller

Next up, we'll actually fetch the product.

```php
// ProductController.php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Http\Requests\ProductDetailRequest;

class ProductController
{
    public function show(ProductDetailRequest $request): Response
    {
       $product = $request->product();

       return Inertia::render('Products/Show', [
           'product' => $product
       ]);
    }
}
```

## Creating a template

Finally, create an Inertia page that receives the product from the
controller.

```jsx
// resources/js/Pages/Products/Show.jsx
export default function Show({ product }) {
    return (
        <main>
            <h1>{product.name}</h1>
            <p>{product.price}</p>
            <p>{product.description}</p>

            <form method="post" action="/cart">
                <input type="hidden" name="product_id" value={product.id} />
                <button type="submit">Add to cart</button>
            </form>
        </main>
    );
}
```
