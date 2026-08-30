<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\Price;
use Larasell\Larasell\Translatable;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var class-string<Model> $productModel */
        $productModel = app(ModelRegistry::class)->product->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $products = $productModel::query()
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Model $product): array => [
                'id' => $product->getKey(),
                'name' => $this->productName($product)->get(),
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
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
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
        $productModel = app(ModelRegistry::class)->product->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('Products/Create', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'productStoreUrl' => route('larasell.admin.products.store'),
            'categories' => $this->categoryOptions($productModel),
            'productAttributes' => $this->productAttributeValueOptions($productModel),
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
        $productModel = app(ModelRegistry::class)->product->class();
        $data = $this->validatedProductData($request);
        $data['slug'] = $this->uniqueSlug($productModel, $data['name']);
        $categoryIds = $data['category_ids'];
        $attributeValueIds = $data['attribute_value_ids'];
        unset($data['category_ids'], $data['attribute_value_ids']);

        $product = DB::transaction(function () use ($categoryIds, $data, $attributeValueIds, $productModel): Model {
            $product = $productModel::query()->create($data);
            $product->categories()->sync($categoryIds);
            $product->attributeValues()->sync($attributeValueIds);

            return $product;
        });

        return redirect()->route('larasell.admin.products.show', $product->getKey());
    }

    public function show(Request $request, string $adminProduct): Response
    {
        /** @var class-string<Model> $productModel */
        $productModel = app(ModelRegistry::class)->product->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $product = $productModel::query()->findOrFail($adminProduct);

        return Inertia::render('Products/Show', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'product' => [
                'id' => $product->getKey(),
                'name' => $this->productName($product)->get(),
                'slug' => $product->getAttribute('slug')->get(),
                'description' => $this->productDescription($product)?->get(),
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
                'variantGenerateUrl' => route('larasell.admin.products.variants.generate', $product->getKey()),
                'variantUpdateUrl' => route('larasell.admin.products.variants.update', $product->getKey()),
                'categoryIds' => $product->categories()->pluck($product->categories()->getRelated()->getQualifiedKeyName())->map(fn ($id): string => (string) $id)->all(),
                'attributeValueIds' => $product->attributeValues()->pluck($product->attributeValues()->getRelated()->getQualifiedKeyName())->map(fn ($id): string => (string) $id)->all(),
            ],
            'categories' => $this->categoryOptions($productModel),
            'productAttributes' => $this->productAttributeValueOptions($productModel),
            'variantDimensionIds' => $product->variantDimensions()->pluck('larasell_product_attributes.id')->map(fn ($id): string => (string) $id)->all(),
            'variants' => $product->variants()
                ->with(['product.variantDimensions', 'attributeValues.attribute'])
                ->where('is_default', false)
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->getKey(),
                    'name' => $variant->snapshotName(),
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'priceAmount' => $variant->price?->amount(),
                    'stock' => $variant->stock,
                    'allowBackorders' => $variant->allow_backorders,
                    'minQuantity' => $variant->min_quantity,
                    'maxQuantity' => $variant->max_quantity,
                    'status' => $variant->status->value,
                ])->all(),
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
        $attributeValueModel = $product->attributeValues()->getRelated();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique($product->getTable(), 'slug->'.App::currentLocale())->ignore($product->getKey())],
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
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['required', 'integer', 'distinct', Rule::exists($attributeValueModel->getTable(), $attributeValueModel->getKeyName())],
        ]);

        $imageIds = array_map('intval', $data['image_order']);
        $newImageIds = array_map('intval', $data['new_image_ids']);
        $categoryIds = array_key_exists('category_ids', $data) ? array_map('intval', $data['category_ids']) : null;
        $attributeValueIds = array_key_exists('attribute_value_ids', $data) ? array_map('intval', $data['attribute_value_ids']) : null;
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
        $data['name'] = $this->productName($product)->with(App::currentLocale(), $data['name']);
        $data['slug'] = $product->slug->with(App::currentLocale(), $data['slug']);
        $data['description'] = $this->translatedDescription($product, $data['description']);
        unset($data['price_amount'], $data['image_order'], $data['new_image_ids'], $data['category_ids'], $data['attribute_value_ids']);

        DB::transaction(function () use ($categoryIds, $data, $imageIds, $newImages, $attributeValueIds, $product): void {
            $product->fill($data)->save();

            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            if ($attributeValueIds !== null) {
                $product->attributeValues()->sync($attributeValueIds);
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique($product->getTable(), 'slug->'.App::currentLocale())->ignore($product->getKey())],
            'description' => ['nullable', 'string'],
        ]);
        $data['name'] = $this->productName($product)->with(App::currentLocale(), $data['name']);
        $data['slug'] = $product->slug->with(App::currentLocale(), $data['slug']);
        $data['description'] = $this->translatedDescription($product, $data['description']);
        $product->fill($data)->save();

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

    public function generateVariants(Request $request, string $adminProduct): RedirectResponse
    {
        $product = $this->findProduct($adminProduct);
        $attributeModel = app(ModelRegistry::class)->productAttribute->class();
        $data = $request->validate([
            'attribute_ids' => ['required', 'array', 'min:1'],
            'attribute_ids.*' => ['required', 'integer', 'distinct', Rule::exists((new $attributeModel)->getTable(), (new $attributeModel)->getKeyName())],
        ]);
        $attributes = $attributeModel::query()
            ->whereKey($data['attribute_ids'])
            ->get()
            ->sortBy(fn (Model $attribute): int => array_search((string) $attribute->getKey(), array_map('strval', $data['attribute_ids']), true))
            ->values()
            ->all();

        try {
            $product->generateVariants($attributes);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['attribute_ids' => $exception->getMessage()]);
        }

        return back();
    }

    public function updateVariants(Request $request, string $adminProduct): RedirectResponse
    {
        $product = $this->findProduct($adminProduct);
        $variantTable = app(ModelRegistry::class)->productVariant->query()->getModel()->getTable();
        $data = $request->validate([
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['required', 'integer', 'distinct'],
            'variants.*.sku' => ['present', 'nullable', 'string', 'max:255'],
            'variants.*.barcode' => ['present', 'nullable', 'string', 'max:255'],
            'variants.*.price_amount' => ['present', 'nullable', 'integer', 'min:0'],
            'variants.*.stock' => ['present', 'nullable', 'integer', 'min:0'],
            'variants.*.allow_backorders' => ['present', 'nullable', 'boolean'],
            'variants.*.min_quantity' => ['present', 'nullable', 'integer', 'min:1'],
            'variants.*.max_quantity' => ['present', 'nullable', 'integer', 'min:1'],
            'variants.*.status' => ['required', Rule::enum(Visibility::class)],
        ]);

        $ids = collect($data['variants'])->pluck('id')->map(fn ($id): int => (int) $id);
        $variants = $product->variants()->whereKey($ids)->get()->keyBy('id');
        if ($variants->count() !== $ids->count()) {
            throw ValidationException::withMessages(['variants' => 'Every variant must belong to this product.']);
        }

        foreach (['sku', 'barcode'] as $identifier) {
            $values = collect($data['variants'])->pluck($identifier)->filter(fn ($value): bool => $value !== null && $value !== '');
            if ($values->duplicates()->isNotEmpty() || DB::table($variantTable)->whereNotIn('id', $ids)->whereIn($identifier, $values)->exists()) {
                throw ValidationException::withMessages(["variants.{$identifier}" => "Variant {$identifier}s must be unique."]);
            }
        }

        DB::transaction(function () use ($data, $variants): void {
            foreach ($data['variants'] as $input) {
                $variant = $variants->get($input['id']);
                if ($input['min_quantity'] !== null && $input['max_quantity'] !== null && $input['min_quantity'] > $input['max_quantity']) {
                    throw ValidationException::withMessages(['variants' => 'Variant minimum quantity cannot exceed maximum quantity.']);
                }
                $variant->update([
                    'sku' => $input['sku'],
                    'barcode' => $input['barcode'],
                    'price' => $input['price_amount'] === null ? null : Price::of($input['price_amount']),
                    'stock' => $input['stock'],
                    'allow_backorders' => $input['allow_backorders'],
                    'min_quantity' => $input['min_quantity'],
                    'max_quantity' => $input['max_quantity'],
                    'status' => $input['status'],
                ]);
            }
        });

        return back();
    }

    private function findProduct(string $id): Model
    {
        /** @var class-string<Model> $productModel */
        $productModel = app(ModelRegistry::class)->product->class();

        return $productModel::query()->findOrFail($id);
    }

    private function productName(Model $product): Translatable
    {
        $name = $product->getAttribute('name');

        if (! $name instanceof Translatable) {
            throw new \LogicException('Product models must cast the name attribute to Translatable.');
        }

        return $name;
    }

    private function productDescription(Model $product): ?Translatable
    {
        $description = $product->getAttribute('description');

        if ($description !== null && ! $description instanceof Translatable) {
            throw new \LogicException('Product models must cast the description attribute to a nullable Translatable.');
        }

        return $description;
    }

    private function translatedDescription(Model $product, ?string $description): ?Translatable
    {
        $translations = $this->productDescription($product);

        if ($description === null) {
            return $translations?->without(App::currentLocale());
        }

        return $translations?->with(App::currentLocale(), $description)
            ?? Translatable::fromString($description);
    }

    /** @param class-string<Model> $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug->'.App::currentLocale(), $slug)->exists()) {
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
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['required', 'integer', 'distinct', Rule::exists($this->attributeValueTable(), $this->attributeValueKeyName())],
        ]);

        $data['price'] = Price::of($data['price_amount']);
        unset($data['price_amount']);
        $data['category_ids'] ??= [];
        $data['attribute_value_ids'] ??= [];

        return $data;
    }

    /** @param class-string<Model> $productModel */
    private function categoryOptions(string $productModel): array
    {
        $categories = $productModel::query()->getModel()->categories()->getRelated()->newQuery()
            ->get()
            ->sortBy(fn (Model $category): string => $category->getAttribute('name')->get(), SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy(fn (Model $category): string => (string) ($category->getAttribute('parent_id') ?? 'root'));

        $buildTree = function (string $parentId = 'root') use (&$buildTree, $categories): array {
            return $categories->get($parentId, collect())
                ->map(fn (Model $category): array => [
                    'label' => $category->getAttribute('name')->get(),
                    'value' => (string) $category->getKey(),
                    'children' => $buildTree((string) $category->getKey()),
                ])
                ->values()
                ->all();
        };

        return $buildTree();
    }

    private function categoryTable(): string
    {
        /** @var class-string<Model> $productModel */
        $productModel = app(ModelRegistry::class)->product->class();

        return $productModel::query()->getModel()->categories()->getRelated()->getTable();
    }

    private function categoryKeyName(): string
    {
        /** @var class-string<Model> $productModel */
        $productModel = app(ModelRegistry::class)->product->class();

        return $productModel::query()->getModel()->categories()->getRelated()->getKeyName();
    }

    /** @param class-string<Model> $productModel */
    private function productAttributeValueOptions(string $productModel): array
    {
        $valueModel = $productModel::query()->getModel()->attributeValues()->getRelated();
        $attributeModel = $valueModel->newInstance()->attribute()->getRelated();

        return $attributeModel->newQuery()
            ->with(['values' => fn ($query) => $query->orderBy('position')->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Model $attribute): array => [
                'id' => (string) $attribute->getKey(),
                'name' => $attribute->getAttribute('name'),
                'type' => $attribute->getAttribute('type')->value,
                'values' => $attribute->getRelation('values')->map(fn (Model $value): array => [
                    'id' => (string) $value->getKey(),
                    'name' => $value->getAttribute('name'),
                    'value' => $value->getAttribute('value'),
                ])->all(),
            ])
            ->all();
    }

    private function attributeValueTable(): string
    {
        return $this->productModelInstance()->attributeValues()->getRelated()->getTable();
    }

    private function attributeValueKeyName(): string
    {
        return $this->productModelInstance()->attributeValues()->getRelated()->getKeyName();
    }

    private function productModelInstance(): Model
    {
        /** @var class-string<Model> $productModel */
        $productModel = app(ModelRegistry::class)->product->class();

        return $productModel::query()->getModel();
    }
}
