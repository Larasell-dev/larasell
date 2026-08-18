<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;
use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;

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
                'slug' => $product->getAttribute('slug'),
                'description' => $product->getAttribute('description'),
                'stock' => $product->getAttribute('stock'),
                'minQuantity' => $product->getAttribute('min_quantity'),
                'maxQuantity' => $product->getAttribute('max_quantity'),
                'allowBackorders' => $product->getAttribute('allow_backorders'),
                'status' => $product->getAttribute('status')->value,
                'price' => $product->getAttribute('price')->toArray(),
                'updateUrl' => route('larasell.admin.products.update', $product->getKey()),
                'generalUpdateUrl' => route('larasell.admin.products.general.update', $product->getKey()),
                'stockUpdateUrl' => route('larasell.admin.products.stock.update', $product->getKey()),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function update(Request $request, string $adminProduct): RedirectResponse
    {
        $product = $this->findProduct($adminProduct);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique($product->getTable(), 'slug')->ignore($product->getKey())],
            'description' => ['nullable', 'string'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1', 'lte:max_quantity'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'gte:min_quantity'],
            'allow_backorders' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(Visibility::class)],
            'price_amount' => ['required', 'numeric', 'min:0'],
            'price_currency' => ['required', Rule::enum(\Larasell\Larasell\Enums\Currency::class)],
        ]);

        $subunit = (new ISOCurrencies)->subunitFor(new MoneyCurrency($data['price_currency']));
        $data['price'] = Price::of((string) round((float) $data['price_amount'] * (10 ** $subunit)), $data['price_currency']);
        unset($data['price_amount'], $data['price_currency']);

        $product->fill($data)->save();

        return back();
    }

    public function updateGeneral(Request $request, string $adminProduct): RedirectResponse
    {
        $product = $this->findProduct($adminProduct);
        $product->fill($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique($product->getTable(), 'slug')->ignore($product->getKey())],
            'description' => ['nullable', 'string'],
        ]))->save();

        return back();
    }

    public function updateStock(Request $request, string $adminProduct): RedirectResponse
    {
        $product = $this->findProduct($adminProduct);
        $data = $request->validate([
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1', 'lte:max_quantity'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'gte:min_quantity'],
            'allow_backorders' => ['required', 'boolean'],
        ]);

        $product->fill($data)->save();

        return back();
    }

    private function findProduct(string $id): Model
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);

        return $productModel::query()->findOrFail($id);
    }
}
