<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $products = $productModel::query()
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Model $product): array => [
                'id' => $product->getKey(),
                'name' => $product->getAttribute('name'),
                'price' => $product->getAttribute('price')->toArray(),
                'stock' => $product->getAttribute('stock'),
                'status' => $product->getAttribute('status')->value,
                'url' => route('larasell.admin.products.show', $product->getKey()),
            ]);

        $productIds = collect($products->items())->pluck('id');

        return Inertia::render('Products/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'products' => $products->items(),
            'productImages' => Inertia::defer(function () use ($productIds, $productModel): array {
                return $productModel::query()
                    ->with('images')
                    ->whereKey($productIds)
                    ->get()
                    ->mapWithKeys(function (Model $product): array {
                        $image = $product->getRelation('images')->first();

                        return [$product->getKey() => $image === null ? null : [
                            'url' => $image->url(),
                            'alt' => $image->getAttribute('alt'),
                        ]];
                    })
                    ->all();
            }),
            'pagination' => [
                'currentPage' => $products->currentPage(),
                'from' => $products->firstItem(),
                'lastPage' => $products->lastPage(),
                'nextUrl' => $products->nextPageUrl(),
                'previousUrl' => $products->previousPageUrl(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function show(Request $request, string $adminProduct): Response
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $product = $productModel::query()->findOrFail($adminProduct);

        return Inertia::render('Products/Show', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'product' => [
                'id' => $product->getKey(),
                'name' => $product->getAttribute('name'),
            ],
        ])->rootView('larasell-admin::admin');
    }
}
