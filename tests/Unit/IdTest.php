<?php

use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Ids\InvalidId;

it('tells IDs of different entities apart, even with the same number', function () {
    expect(new ProductId(5)->equals(new ProductId(5)))->toBeTrue()
        ->and(new ProductId(5)->equals(new ProductId(6)))->toBeFalse()
        ->and(new ProductId(5)->equals(new CategoryId(5)))->toBeFalse();
});

it('takes the ID of its own model only', function () {
    $product = new Product;
    $product->id = 7;
    $category = new Category;
    $category->id = 7;

    expect(ProductId::of($product)->value)->toBe(7);

    try {
        ProductId::of($category);
        $this->fail('No exception thrown.');
    } catch (InvalidId $exception) {
        expect($exception->errorCode())->toBe('larasell.id.wrong-model')
            ->and($exception->getMessage())->toContain(Category::class)
            ->and($exception->errorFix())->not->toBeNull();
    }
});

it('refuses a model that was never saved', function () {
    ProductId::of(new Product);
})->throws(InvalidId::class, 'not saved');

it('parses an ID from request input, and refuses anything that is not one', function (string $input, ?string $code) {
    if ($code === null) {
        expect(ProductId::fromString($input)->value)->toBe((int) $input);

        return;
    }

    try {
        ProductId::fromString($input);
        $this->fail('No exception thrown.');
    } catch (InvalidId $exception) {
        expect($exception->errorCode())->toBe($code);
    }
})->with([
    'a number' => ['42', null],
    'zero' => ['0', 'larasell.id.not-positive'],
    'negative' => ['-3', 'larasell.id.malformed'],
    'leading zero' => ['042', 'larasell.id.malformed'],
    'decimal' => ['4.2', 'larasell.id.malformed'],
    'text' => ['abc', 'larasell.id.malformed'],
    'empty' => ['', 'larasell.id.malformed'],
    'too large' => ['99999999999999999999', 'larasell.id.malformed'],
]);

it('refuses zero and negative numbers', function () {
    new ProductId(0);
})->throws(InvalidId::class, 'positive');

it('writes itself as its number, and lists the numbers of many', function () {
    expect(json_encode(['id' => new ProductId(42)]))->toBe('{"id":42}')
        ->and((string) new ProductId(42))->toBe('42')
        ->and(ProductId::values([new ProductId(3), new ProductId(1)]))->toBe([3, 1])
        ->and(ProductId::many([3, 1]))->toEqual([new ProductId(3), new ProductId(1)]);
});
