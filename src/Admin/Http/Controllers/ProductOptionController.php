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
use Larasell\Larasell\Enums\ProductOptionType;
use Larasell\Larasell\Models\ProductOption;

class ProductOptionController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var class-string<Model> $productOptionModel */
        $productOptionModel = config('larasell.models.product_option', ProductOption::class);
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        $productOptions = $productOptionModel::query()
            ->withCount('values')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Model $productOption): array => [
                'id' => $productOption->getKey(),
                'name' => $productOption->getAttribute('name'),
                'type' => $productOption->getAttribute('type')->value,
                'url' => route('larasell.admin.product-options.show', $productOption->getKey()),
                'deleteUrl' => route('larasell.admin.product-options.destroy', $productOption->getKey()),
                'valuesCount' => $productOption->getAttribute('values_count'),
            ]);

        return Inertia::render('ProductOptions/Index', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'productOptionCreateUrl' => route('larasell.admin.product-options.create'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'productOptions' => $productOptions->items(),
            'pagination' => [
                'currentPage' => $productOptions->currentPage(),
                'from' => $productOptions->firstItem(),
                'lastPage' => $productOptions->lastPage(),
                'nextUrl' => $productOptions->nextPageUrl(),
                'previousUrl' => $productOptions->previousPageUrl(),
                'to' => $productOptions->lastItem(),
                'total' => $productOptions->total(),
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function create(Request $request): Response
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        return Inertia::render('ProductOptions/Create', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'productOptionStoreUrl' => route('larasell.admin.product-options.store'),
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        /** @var class-string<Model> $productOptionModel */
        $productOptionModel = config('larasell.models.product_option', ProductOption::class);

        $productOption = DB::transaction(function () use ($data, $productOptionModel): Model {
            $productOption = $productOptionModel::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($productOptionModel, $data['name']),
                'type' => $data['type'],
            ]);

            $this->syncValues($productOption, $data);

            return $productOption;
        });

        return redirect()->route('larasell.admin.product-options.show', $productOption->getKey());
    }

    public function show(Request $request, string $adminProductOption): Response
    {
        /** @var class-string<Model> $productOptionModel */
        $productOptionModel = config('larasell.models.product_option', ProductOption::class);
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));
        $productOption = $productOptionModel::query()->with(['values' => fn ($query) => $query->orderBy('position')])->findOrFail($adminProductOption);

        return Inertia::render('ProductOptions/Show', [
            'homeUrl' => route('larasell.admin.home'),
            'productsUrl' => route('larasell.admin.products.index'),
            'productOptionsUrl' => route('larasell.admin.product-options.index'),
            'productOption' => [
                'name' => $productOption->getAttribute('name'),
                'type' => $productOption->getAttribute('type')->value,
                'updateUrl' => route('larasell.admin.product-options.update', $productOption->getKey()),
                'values' => $productOption->getRelation('values')->map(fn (Model $value): array => [
                    'id' => $value->getKey(),
                    'value' => $value->getAttribute('value'),
                ])->all(),
            ],
            'logoutUrl' => route('larasell.admin.logout'),
            'user' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
        ])->rootView('larasell-admin::admin');
    }

    public function update(Request $request, string $adminProductOption): RedirectResponse
    {
        /** @var class-string<Model> $productOptionModel */
        $productOptionModel = config('larasell.models.product_option', ProductOption::class);
        $productOption = $productOptionModel::query()->findOrFail($adminProductOption);
        $data = $this->validatedData($request, $productOption);

        DB::transaction(function () use ($data, $productOption): void {
            $productOption->setAttribute('name', $data['name']);
            $productOption->setAttribute('type', $data['type']);
            $productOption->save();
            $this->syncValues($productOption, $data);
        });

        return back();
    }

    public function destroy(string $adminProductOption): RedirectResponse
    {
        /** @var class-string<Model> $productOptionModel */
        $productOptionModel = config('larasell.models.product_option', ProductOption::class);
        $productOptionModel::query()->findOrFail($adminProductOption)->delete();

        return redirect()->route('larasell.admin.product-options.index');
    }

    /** @param class-string<Model> $model */
    private function uniqueSlug(string $model, string $name): string
    {
        $base = Str::slug($name) ?: 'option';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function uniqueValueSlug(Model $productOption, string $name): string
    {
        $base = Str::slug($name) ?: 'value';
        $slug = $base;
        $suffix = 2;

        while ($productOption->values()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @return array{name: string, type: string, options?: array<int, array{id?: mixed, value: mixed}>} */
    private function validatedData(Request $request, ?Model $productOption = null): array
    {
        $request->merge([
            'options' => collect($request->input('options', []))
                ->filter(function (mixed $option): bool {
                    if (! is_array($option) || ! array_key_exists('value', $option)) {
                        return false;
                    }

                    $value = $option['value'];

                    return $value !== null && (! is_string($value) || trim($value) !== '');
                })
                ->values()
                ->all(),
        ]);

        $valueModel = $productOption?->values()->getRelated();
        $idRules = $productOption === null
            ? ['prohibited']
            : ['sometimes', 'integer', 'distinct'];

        if ($valueModel !== null && $productOption !== null) {
            $idRules[] = Rule::exists($valueModel->getTable(), $valueModel->getKeyName())
                ->where('product_option_id', $productOption->getKey());
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductOptionType::class)],
            'options' => ['array', 'max:100'],
            'options.*.id' => $idRules,
            'options.*.value' => [
                'required',
                'distinct:strict',
                Rule::when($request->input('type') === ProductOptionType::Number->value, ['numeric'], ['string', 'max:255']),
            ],
        ]);
    }

    /** @param array{name: string, type: string, options?: array<int, array{id?: mixed, value: mixed}>} $data */
    private function syncValues(Model $productOption, array $data): void
    {
        $keptIds = [];

        foreach ($data['options'] ?? [] as $position => $option) {
            $value = $data['type'] === ProductOptionType::Number->value
                ? (float) $option['value']
                : $option['value'];
            $attributes = [
                'name' => (string) $value,
                'value' => $value,
                'position' => $position,
            ];

            if (isset($option['id'])) {
                $existingValue = $productOption->values()->findOrFail($option['id']);
                $existingValue->fill($attributes)->save();
                $keptIds[] = $existingValue->getKey();
            } else {
                $attributes['slug'] = $this->uniqueValueSlug($productOption, (string) $value);
                $keptIds[] = $productOption->values()->create($attributes)->getKey();
            }
        }

        $productOption->values()->whereNotIn($productOption->values()->getRelated()->getKeyName(), $keptIds)->delete();
    }
}
