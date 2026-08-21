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
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productCreateUrl' => route('larasell.admin.products.create'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
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
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('Products/Create', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'productStoreUrl' => route('larasell.admin.products.store'),
            'categories' => $this->categoryOptions($productModel),
            'productOptions' => $this->productOptionValueOptions($productModel),
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
        $categoryIds = $data['category_ids'];
        $optionValueIds = $data['option_value_ids'];
        unset($data['category_ids'], $data['option_value_ids']);

        $product = DB::transaction(function () use ($categoryIds, $data, $optionValueIds, $productModel): Model {
            $product = $productModel::query()->create($data);
            $product->categories()->sync($categoryIds);
            $product->optionValues()->sync($optionValueIds);

            return $product;
        });

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
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
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
                'categoryIds' => $product->categories()->pluck($product->categories()->getRelated()->getQualifiedKeyName())->map(fn ($id): string => (string) $id)->all(),
                'optionValueIds' => $product->optionValues()->pluck($product->optionValues()->getRelated()->getQualifiedKeyName())->map(fn ($id): string => (string) $id)->all(),
            ],
            'categories' => $this->categoryOptions($productModel),
            'productOptions' => $this->productOptionValueOptions($productModel),
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
        $categoryModel = $product->categories()->getRelated();
        $optionValueModel = $product->optionValues()->getRelated();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique($product->getTable(), 'slug')->ignore($product->getKey())],
            'description' => ['nullable', 'string'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1', 'lte:max_quantity'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'gte:min_quantity'],
            'allow_backorders' => ['required', 'boolean'],
            'status' => ['required', Rule::enum(Visibility::class)],
            'price_amount' => ['required', 'integer', 'min:0'],
            'image_order' => ['present', 'array'],
            'image_order.*' => ['required', 'integer', 'distinct', Rule::exists($imageModel->getTable(), $imageModel->getKeyName())],
            'new_image_ids' => ['present', 'array'],
            'new_image_ids.*' => ['required', 'integer', 'distinct', Rule::exists($imageModel->getTable(), $imageModel->getKeyName())],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists($categoryModel->getTable(), $categoryModel->getKeyName())],
            'option_value_ids' => ['sometimes', 'array'],
            'option_value_ids.*' => ['required', 'integer', 'distinct', Rule::exists($optionValueModel->getTable(), $optionValueModel->getKeyName())],
        ]);

        $imageIds = array_map('intval', $data['image_order']);
        $newImageIds = array_map('intval', $data['new_image_ids']);
        $categoryIds = array_key_exists('category_ids', $data) ? array_map('intval', $data['category_ids']) : null;
        $optionValueIds = array_key_exists('option_value_ids', $data) ? array_map('intval', $data['option_value_ids']) : null;
        $attachedImageIds = $product->images()->pluck($imageModel->getQualifiedKeyName())->map(fn ($id): int => (int) $id)->all();
        $expectedImageIds = [...$attachedImageIds, ...$newImageIds];

        if (count($expectedImageIds) > 11 || count($expectedImageIds) !== count(array_unique($expectedImageIds)) || collect($expectedImageIds)->sort()->values()->all() !== collect($imageIds)->sort()->values()->all()) {
            throw ValidationException::withMessages(['image_order' => 'The image order must contain every product image exactly once.']);
        }

        $newImages = $imageModel->newQuery()->whereKey($newImageIds)->get()->keyBy(fn (Model $image) => (string) $image->getKey());
        if ($newImages->count() !== count($newImageIds) || $newImages->contains(fn (Model $image): bool => (string) data_get($image->getAttribute('meta'), 'pending_product_id') !== (string) $product->getKey())) {
            throw ValidationException::withMessages(['new_image_ids' => 'One or more uploaded images do not belong to this product.']);
        }

        $data['price'] = Price::of($data['price_amount']);
        unset($data['price_amount'], $data['image_order'], $data['new_image_ids'], $data['category_ids'], $data['option_value_ids']);

        DB::transaction(function () use ($categoryIds, $data, $imageIds, $newImages, $optionValueIds, $product): void {
            $product->fill($data)->save();

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            if ($optionValueIds !== null) {
                $product->optionValues()->sync($optionValueIds);
            }

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
            'price_amount' => ['required', 'integer', 'min:0'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists($this->categoryTable(), $this->categoryKeyName())],
            'option_value_ids' => ['sometimes', 'array'],
            'option_value_ids.*' => ['required', 'integer', 'distinct', Rule::exists($this->optionValueTable(), $this->optionValueKeyName())],
        ]);

        $data['price'] = Price::of($data['price_amount']);
        unset($data['price_amount']);
        $data['category_ids'] ??= [];
        $data['option_value_ids'] ??= [];

        return $data;
    }

    /** @param class-string<Model> $productModel */
    private function categoryOptions(string $productModel): array
    {
        return $productModel::query()->getModel()->categories()->getRelated()->newQuery()
            ->orderBy('name')
            ->get()
            ->map(fn (Model $category): array => [
                'label' => $category->getAttribute('name'),
                'value' => (string) $category->getKey(),
            ])
            ->all();
    }

    private function categoryTable(): string
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);

        return $productModel::query()->getModel()->categories()->getRelated()->getTable();
    }

    private function categoryKeyName(): string
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);

        return $productModel::query()->getModel()->categories()->getRelated()->getKeyName();
    }

    /** @param class-string<Model> $productModel */
    private function productOptionValueOptions(string $productModel): array
    {
        $valueModel = $productModel::query()->getModel()->optionValues()->getRelated();
        $optionModel = $valueModel->newInstance()->option()->getRelated();

        return $optionModel->newQuery()
            ->with(['values' => fn ($query) => $query->orderBy('position')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Model $option): array => [
                'id' => (string) $option->getKey(),
                'name' => $option->getAttribute('name'),
                'type' => $option->getAttribute('type')->value,
                'values' => $option->getRelation('values')->map(fn (Model $value): array => [
                    'id' => (string) $value->getKey(),
                    'name' => $value->getAttribute('name'),
                    'value' => $value->getAttribute('value'),
                ])->all(),
            ])
            ->all();
    }

    private function optionValueTable(): string
    {
        return $this->productModelInstance()->optionValues()->getRelated()->getTable();
    }

    private function optionValueKeyName(): string
    {
        return $this->productModelInstance()->optionValues()->getRelated()->getKeyName();
    }

    private function productModelInstance(): Model
    {
        /** @var class-string<Model> $productModel */
        $productModel = config('larasell.models.product', Product::class);

        return $productModel::query()->getModel();
    }
}
