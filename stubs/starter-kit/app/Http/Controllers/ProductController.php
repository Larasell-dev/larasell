<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Http\Requests\ProductDetailRequest;
use Larasell\Larasell\Http\Requests\ProductListingRequest;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\ProductImage;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;
use Larasell\Larasell\Settings\CurrencySettings;

class ProductController extends Controller
{
    public function show(ProductDetailRequest $request, CurrencySettings $currencies): Response
    {
        $product = $request->product()->load(['images', 'visibleVariants']);
        $currency = $currencies->enabled()[0];
        $locale = App::currentLocale();

        return Inertia::render('Products/Show', [
            'product' => [
                'name' => $product->name->get(),
                'description' => $product->description?->get(),
                'images' => $product->images->map(fn (ProductImage $image): array => [
                    'id' => $image->getKey(),
                    'alt' => $image->alt,
                    'url' => $image->url(),
                ])->values()->all(),
                'variants' => $product->visibleVariants->map(function (ProductVariant $variant) use ($currency, $locale): array {
                    $compareAt = $variant->compareAtPrice();

                    return [
                        'id' => $variant->getKey(),
                        'name' => $variant->name(),
                        'price' => Price::format($variant->unitPrice(), $currency, $locale),
                        'compareAt' => $compareAt !== null && $variant->onSale()
                            ? Price::format($compareAt, $currency, $locale)
                            : null,
                        'minQuantity' => $variant->minimumQuantity() ?? 1,
                        'maxQuantity' => $variant->maximumQuantity(),
                    ];
                })->all(),
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
                    $thumbnail = $product->thumbnail;
                    $compareAt = $product->compare_at;

                    return [
                        'id' => $product->getKey(),
                        'name' => $product->name->get(),
                        'slug' => $product->slug->get(),
                        'price' => Price::format($product->price, $currency, $locale),
                        'compareAt' => $compareAt !== null && $product->onSale()
                            ? Price::format($compareAt, $currency, $locale)
                            : null,
                        'image' => $thumbnail === null ? null : [
                            'alt' => $thumbnail->alt,
                            'url' => $thumbnail->url(),
                        ],
                    ];
                })
                ->all(),
            'sort' => $request->sort(),
        ]);
    }
}
