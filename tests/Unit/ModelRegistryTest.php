<?php

use Illuminate\Database\Eloquent\Builder;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Product;

class ConfiguredProduct extends Product {}

it('provides the configured model class, instance, and query', function () {
    config()->set('larasell.models.product', ConfiguredProduct::class);

    $products = app(ModelRegistry::class)->product;

    expect($products->class())->toBe(ConfiguredProduct::class)
        ->and($products->new())->toBeInstanceOf(ConfiguredProduct::class)
        ->and($products->query())->toBeInstanceOf(Builder::class)
        ->and($products->query()->getModel())->toBeInstanceOf(ConfiguredProduct::class);
});

it('uses updated model configuration after the registry is resolved', function () {
    $models = app(ModelRegistry::class);

    config()->set('larasell.models.product', ConfiguredProduct::class);

    expect($models->product->class())->toBe(ConfiguredProduct::class);
});
