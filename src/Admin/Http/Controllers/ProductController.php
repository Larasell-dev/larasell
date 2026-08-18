<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
                'deleteUrl' => route('larasell.admin.products.destroy', $product->getKey()),
            ]);

        $productIds = collect($products->items())->pluck('id');

        return Inertia::render('Products/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productCreateUrl' => route('larasell.admin.products.create'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
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

    public function create(Request $request): Response
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('Products/Create', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'productStoreUrl' => route('larasell.admin.products.store'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);
        $data = $this->validatedProductData($request);
        $data['slug'] = $this->uniqueSlug($productModel, $data['name']);

        $product = $productModel::query()->create($data);

        return redirect()->route('larasell.admin.products.show', $product->getKey());
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
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
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
                'imageUploadUrl' => route('larasell.admin.products.images.store', $product->getKey()),
                'generalUpdateUrl' => route('larasell.admin.products.general.update', $product->getKey()),
                'stockUpdateUrl' => route('larasell.admin.products.stock.update', $product->getKey()),
            ],
            'images' => Inertia::defer(fn (): array => $product->images()
                ->get()
                ->map(fn (Model $image): array => [
                    'id' => $image->getKey(),
                    'url' => $image->url(),
                    'alt' => $image->getAttribute('alt'),
                ])
                ->all()),
        ])->rootView('larasell-admin::admin');
    }

    public function update(Request $request, string $adminProduct): RedirectResponse
    {
        $product = $this->findProduct($adminProduct);
        $imageModel = $product->images()->getRelated();
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
            'image_order' => ['present', 'array'],
            'image_order.*' => ['required', 'integer', 'distinct', Rule::exists($imageModel->getTable(), $imageModel->getKeyName())],
            'new_image_ids' => ['present', 'array'],
            'new_image_ids.*' => ['required', 'integer', 'distinct', Rule::exists($imageModel->getTable(), $imageModel->getKeyName())],
        ]);

        $imageIds = array_map('intval', $data['image_order']);
        $newImageIds = array_map('intval', $data['new_image_ids']);
        $attachedImageIds = $product->images()->pluck($imageModel->getQualifiedKeyName())->map(fn ($id): int => (int) $id)->all();
        $expectedImageIds = [...$attachedImageIds, ...$newImageIds];

        if (count($expectedImageIds) > 11 || count($expectedImageIds) !== count(array_unique($expectedImageIds)) || collect($expectedImageIds)->sort()->values()->all() !== collect($imageIds)->sort()->values()->all()) {
            throw ValidationException::withMessages(['image_order' => 'The image order must contain every product image exactly once.']);
        }

        $newImages = $imageModel->newQuery()->whereKey($newImageIds)->get()->keyBy(fn (Model $image) => (string) $image->getKey());
        if ($newImages->count() !== count($newImageIds) || $newImages->contains(fn (Model $image): bool => (string) data_get($image->getAttribute('meta'), 'pending_product_id') !== (string) $product->getKey())) {
            throw ValidationException::withMessages(['new_image_ids' => 'One or more uploaded images do not belong to this product.']);
        }

        $subunit = (new ISOCurrencies)->subunitFor(new MoneyCurrency($data['price_currency']));
        $data['price'] = Price::of((string) round((float) $data['price_amount'] * (10 ** $subunit)), $data['price_currency']);
        unset($data['price_amount'], $data['price_currency'], $data['image_order'], $data['new_image_ids']);

        DB::transaction(function () use ($data, $imageIds, $newImages, $product): void {
            $product->fill($data)->save();

            foreach ($imageIds as $position => $imageId) {
                $newImage = $newImages->get((string) $imageId);

                if ($newImage) {
                    $product->images()->attach($newImage, ['position' => $position]);
                    $meta = $newImage->getAttribute('meta') ?? [];
                    unset($meta['pending_product_id']);
                    $newImage->setAttribute('meta', $meta)->save();
                } else {
                    $product->images()->updateExistingPivot($imageId, ['position' => $position]);
                }
            }
        });

        return back();
    }

    public function destroy(string $adminProduct): RedirectResponse
    {
        $this->findProduct($adminProduct)->delete();

        return redirect()->route('larasell.admin.products.index');
    }

    public function storeImage(Request $request, string $adminProduct): JsonResponse
    {
        $product = $this->findProduct($adminProduct);

        if ($product->images()->count() >= 11) {
            throw ValidationException::withMessages(['image' => 'A product can have at most 11 images.']);
        }

        $file = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ])['image'];
        $disk = config('larasell.images.disk');
        $path = $file->store('products/'.$product->getKey(), $disk);

        try {
            $image = DB::transaction(function () use ($file, $path, $product): Model {
                $image = $product->images()->getRelated()->newInstance([
                    'path' => $path,
                    'alt' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'meta' => [
                        'mime_type' => $file->getMimeType(),
                        'original_name' => $file->getClientOriginalName(),
                        'pending_product_id' => (string) $product->getKey(),
                    ],
                ]);
                $image->save();

                return $image;
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return response()->json([
            'image' => [
                'id' => $image->getKey(),
                'url' => $image->url(),
                'alt' => $image->getAttribute('alt'),
            ],
        ], 201);
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

    /** @param class-string<Model> $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function validatedProductData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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

        return $data;
    }
}
