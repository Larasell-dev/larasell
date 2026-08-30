<?php

use Larasell\Larasell\Discounts\ProportionalDiscountAllocator;
use Larasell\Larasell\Price;

it('allocates a fixed discount proportionally between eligible targets', function () {
    $allocations = app(ProportionalDiscountAllocator::class)->allocate(Price::of(1000), [
        'line:1' => Price::of(6000),
        'line:2' => Price::of(4000),
    ]);

    expect($allocations)->toHaveCount(2)
        ->and($allocations[0]->target)->toBe('line:1')
        ->and($allocations[0]->amount->amount())->toBe('600')
        ->and($allocations[1]->target)->toBe('line:2')
        ->and($allocations[1]->amount->amount())->toBe('400');
});

it('distributes remainder units deterministically regardless of input order', function () {
    $allocator = app(ProportionalDiscountAllocator::class);

    $first = $allocator->allocate(Price::of(2), [
        'line:c' => Price::of(1),
        'line:a' => Price::of(1),
        'line:b' => Price::of(1),
    ]);
    $second = $allocator->allocate(Price::of(2), [
        'line:b' => Price::of(1),
        'line:c' => Price::of(1),
        'line:a' => Price::of(1),
    ]);

    $amounts = fn (array $allocations): array => collect($allocations)
        ->mapWithKeys(fn ($allocation): array => [$allocation->target => $allocation->amount->amount()])
        ->all();

    expect($amounts($first))->toBe([
        'line:a' => '1',
        'line:b' => '1',
    ])->and($amounts($second))->toBe($amounts($first));
});

it('caps a discount at the total eligible amount', function () {
    $allocations = app(ProportionalDiscountAllocator::class)->allocate(Price::of(12000), [
        'line:1' => Price::of(6000),
        'line:2' => Price::of(4000),
    ]);

    expect($allocations[0]->amount->amount())->toBe('6000')
        ->and($allocations[1]->amount->amount())->toBe('4000')
        ->and(collect($allocations)->reduce(
            fn (Price $total, $allocation): Price => $total->add($allocation->amount),
            Price::of(0),
        )->amount())->toBe('10000');
});

it('ignores targets with no eligible amount', function () {
    $allocations = app(ProportionalDiscountAllocator::class)->allocate(Price::of(500), [
        'line:1' => Price::of(1000),
        'line:2' => Price::of(0),
    ]);

    expect($allocations)->toHaveCount(1)
        ->and($allocations[0]->target)->toBe('line:1')
        ->and($allocations[0]->amount->amount())->toBe('500');
});

it('returns no allocations for a zero discount or no eligible value', function (Price $discount, array $eligible) {
    expect(app(ProportionalDiscountAllocator::class)->allocate($discount, $eligible))->toBe([]);
})->with([
    'zero discount' => [Price::of(0), ['line:1' => Price::of(1000)]],
    'no targets' => [Price::of(100), []],
    'zero-value targets' => [Price::of(100), ['line:1' => Price::of(0)]],
]);

it('rejects negative discounts and eligible amounts', function (Price $discount, array $eligible, string $message) {
    expect(fn () => app(ProportionalDiscountAllocator::class)->allocate($discount, $eligible))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'negative discount' => [Price::of(-1), ['line:1' => Price::of(100)], 'A discount amount cannot be negative.'],
    'negative eligible amount' => [Price::of(10), ['line:1' => Price::of(-1)], 'An eligible amount cannot be negative.'],
]);
