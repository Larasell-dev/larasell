<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Enums\ProductAttributeType;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\ProductAttribute;

class ProductAttributeController extends Controller
{
    private const BOOLEAN_FALSE_VALUE_SLUG = '__boolean_false';

    private const BOOLEAN_TRUE_VALUE_SLUG = '__boolean_true';

    public function index(Request $request): Response
    {
        $productAttributeModel = app(ModelRegistry::class)->productAttribute->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $productAttributes = $productAttributeModel::query()
            ->withCount('values')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Model $productAttribute): array => [
                'id' => $productAttribute->getKey(),
                'name' => $productAttribute->getAttribute('name'),
                'type' => $productAttribute->getAttribute('type')->value,
                'url' => route('larasell.admin.product-attributes.show', $productAttribute->getKey()),
                'deleteUrl' => route('larasell.admin.product-attributes.destroy', $productAttribute->getKey()),
                'valuesCount' => $productAttribute->getAttribute('values_count'),
            ]);

        return Inertia::render('ProductAttributes/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'productAttributeCreateUrl' => route('larasell.admin.product-attributes.create'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
            ],
            'productAttributes' => $productAttributes->items(),
            'pagination' => [
                'currentPage' => $productAttributes->currentPage(),
                'from' => $productAttributes->firstItem(),
                'lastPage' => $productAttributes->lastPage(),
                'nextUrl' => $productAttributes->nextPageUrl(),
                'previousUrl' => $productAttributes->previousPageUrl(),
                'to' => $productAttributes->lastItem(),
                'total' => $productAttributes->total(),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function create(Request $request): Response
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('ProductAttributes/Create', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'productAttributeStoreUrl' => route('larasell.admin.product-attributes.store'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $productAttributeModel = app(ModelRegistry::class)->productAttribute->class();

        $productAttribute = DB::transaction(function () use ($data, $productAttributeModel): ProductAttribute {
            $productAttribute = $productAttributeModel::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($productAttributeModel, $data['name']),
                'type' => $data['type'],
            ]);

            $this->syncValues($productAttribute, $data);

            return $productAttribute;
        });

        return redirect()->route('larasell.admin.product-attributes.show', $productAttribute->getKey());
    }

    public function show(Request $request, string $adminProductAttribute): Response
    {
        $productAttributeModel = app(ModelRegistry::class)->productAttribute->class();
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $productAttribute = $productAttributeModel::query()->with(['values' => fn ($query) => $query->orderBy('position')])->findOrFail($adminProductAttribute);

        return Inertia::render('ProductAttributes/Show', [
            'homeUrl' => route('larasell.admin.home'),
            'mediaUrl' => route('larasell.admin.media.index'),
            'ordersUrl' => route('larasell.admin.orders.index'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productAttributesUrl' => route('larasell.admin.product-attributes.index'),
            'settingsUrl' => route('larasell.admin.settings.index'),
            'productAttribute' => [
                'name' => $productAttribute->getAttribute('name'),
                'type' => $productAttribute->getAttribute('type')->value,
                'updateUrl' => route('larasell.admin.product-attributes.update', $productAttribute->getKey()),
                'values' => $productAttribute->getRelation('values')->map(fn (Model $value): array => [
                    'id' => $value->getKey(),
                    'value' => $value->getAttribute('value'),
                ])->all(),
            ],
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->getAttribute('name'),
                'email' => $admin->getAttribute('email'),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function update(Request $request, string $adminProductAttribute): RedirectResponse
    {
        $productAttributeModel = app(ModelRegistry::class)->productAttribute->class();
        $productAttribute = $productAttributeModel::query()->findOrFail($adminProductAttribute);
        $data = $this->validatedData($request, $productAttribute);

        DB::transaction(function () use ($data, $productAttribute): void {
            $productAttribute->setAttribute('name', $data['name']);
            $productAttribute->setAttribute('type', $data['type']);
            $productAttribute->save();
            $this->syncValues($productAttribute, $data);
        });

        return back();
    }

    public function destroy(string $adminProductAttribute): RedirectResponse
    {
        $productAttributeModel = app(ModelRegistry::class)->productAttribute->class();
        $productAttributeModel::query()->findOrFail($adminProductAttribute)->delete();

        return redirect()->route('larasell.admin.product-attributes.index');
    }

    /** @param class-string<ProductAttribute> $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'value';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueValueSlug(ProductAttribute $productAttribute, string $name): string
    {
        $base = Str::slug($name) ?: 'value';
        $slug = $base;
        $suffix = 2;

        while ($productAttribute->values()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array{name: string, type: string, values?: array<int, array{id?: mixed, value: mixed}>} */
    private function validatedData(Request $request, ?ProductAttribute $productAttribute = null): array
    {
        $request->merge([
            'values' => collect($request->input('values', []))
                ->filter(function (mixed $value): bool {
                    if (! is_array($value) || ! array_key_exists('value', $value)) {
                        return false;
                    }

                    $value = $value['value'];

                    return $value !== null && (! is_string($value) || trim($value) !== '');
                })
                ->values()
                ->all(),
        ]);

        $valueModel = $productAttribute?->values()->getRelated();
        $idRules = $productAttribute === null
            ? ['prohibited']
            : ['sometimes', 'integer', 'distinct'];

        if ($valueModel !== null && $productAttribute !== null) {
            $idRules[] = Rule::exists($valueModel->getTable(), $valueModel->getKeyName())
                ->where('product_attribute_id', $productAttribute->getKey());
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductAttributeType::class)],
            'values' => ['array', 'max:100'],
            'values.*.id' => $idRules,
            'values.*.value' => [
                'required',
                'distinct:strict',
                Rule::when($request->input('type') === ProductAttributeType::Number->value, ['numeric'], ['string', 'max:255']),
            ],
        ]);
    }

    /** @param array{name: string, type: string, values?: array<int, array{id?: mixed, value: mixed}>} $data */
    private function syncValues(ProductAttribute $productAttribute, array $data): void
    {
        if ($data['type'] === ProductAttributeType::Boolean->value) {
            $trueValue = $productAttribute->values()->updateOrCreate(
                ['slug' => self::BOOLEAN_TRUE_VALUE_SLUG],
                ['name' => 'Yes', 'value' => true, 'position' => 0],
            );
            $falseValue = $productAttribute->values()->updateOrCreate(
                ['slug' => self::BOOLEAN_FALSE_VALUE_SLUG],
                ['name' => 'No', 'value' => false, 'position' => 1],
            );

            $productAttribute->values()
                ->whereNotIn($productAttribute->values()->getRelated()->getKeyName(), [$trueValue->getKey(), $falseValue->getKey()])
                ->delete();

            return;
        }

        $keptIds = [];

        foreach ($data['values'] ?? [] as $position => $input) {
            $value = $data['type'] === ProductAttributeType::Number->value
                ? (float) $input['value']
                : $input['value'];
            $attributes = [
                'name' => (string) $value,
                'value' => $value,
                'position' => $position,
            ];

            if (isset($input['id'])) {
                $existingValue = $productAttribute->values()->findOrFail($input['id']);
                $existingValue->fill($attributes)->save();
                $keptIds[] = $existingValue->getKey();
            } else {
                $attributes['slug'] = $this->uniqueValueSlug($productAttribute, (string) $value);
                $keptIds[] = $productAttribute->values()->create($attributes)->getKey();
            }
        }

        $productAttribute->values()->whereNotIn($productAttribute->values()->getRelated()->getKeyName(), $keptIds)->delete();
    }
}
