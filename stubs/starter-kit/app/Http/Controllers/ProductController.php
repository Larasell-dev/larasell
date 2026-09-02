<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Http\Requests\ProductListingRequest;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Settings\CurrencySettings;

class ProductController extends Controller
{
    public function index(ProductListingRequest $request, CurrencySettings $currencies): Response
    {
        $category = $request->category();

        return Inertia::render('Products/Index', [
            'category' => [
                'name' => $category->name->get(),
            ],
            'currency' => $currencies->enabled()[0]->value,
            'products' => $request->products()
                ->with('images')
                ->get()
                ->map(function (Product $product): array {
                    $image = $product->images->first();

                    return [
                        'id' => $product->getKey(),
                        'name' => $product->name->get(),
                        'slug' => $product->slug->get(),
                        'price' => $product->price->toArray(),
                        'image' => $image === null ? null : [
                            'alt' => $image->alt,
                            'url' => $image->url(),
                        ],
                    ];
                })
                ->all(),
            'sort' => $request->sort(),
        ]);
    }
}
