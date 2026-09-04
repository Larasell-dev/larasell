<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Http\Requests\ProductDetailRequest;
use Larasell\Larasell\Http\Requests\ProductListingRequest;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Larasell\Larasell\Settings\CurrencySettings;

class ProductController extends Controller
{
    public function show(ProductDetailRequest $request, CurrencySettings $currencies): Response
    {
        $product = $request->product()->load('images');
        $currency = $currencies->enabled()[0];
        $locale = App::currentLocale();
        $image = $product->images->first();

        return Inertia::render('Products/Show', [
            'product' => [
                'id' => $product->getKey(),
                'name' => $product->name->get(),
                'description' => $product->description?->get(),
                'price' => Price::format($product->price, $currency, $locale),
                'minQuantity' => $product->min_quantity ?? 1,
                'maxQuantity' => $product->max_quantity,
                'image' => $image === null ? null : [
                    'alt' => $image->alt,
                    'url' => $image->url(),
                ],
            ],
        ]);
    }

    public function index(ProductListingRequest $request, CurrencySettings $currencies): Response
    {
        $category = $request->category();
        $currency = $currencies->enabled()[0];
        $locale = App::currentLocale();

        return Inertia::render('Products/Index', [
            'category' => [
                'name' => $category->name->get(),
            ],
            'products' => $request->products()
                ->with('images')
                ->get()
                ->map(function (Product $product) use ($currency, $locale): array {
                    $image = $product->images->first();

                    return [
                        'id' => $product->getKey(),
                        'name' => $product->name->get(),
                        'slug' => $product->slug->get(),
                        'price' => Price::format($product->price, $currency, $locale),
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
