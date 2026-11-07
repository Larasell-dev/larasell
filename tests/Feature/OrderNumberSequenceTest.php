<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\OrderNumbers\MultipleOrderNumberSequence;
use Larasell\Larasell\OrderNumbers\SingleOrderNumberSequence;

function orderNumberCounters(): array
{
    return DB::table('larasell_order_number_sequences')->orderBy('name')->pluck('number', 'name')
        ->map(fn ($number): int => (int) $number)
        ->all();
}

it('allocates the default sequence when no name is given', function () {
    $sequences = app(SingleOrderNumberSequence::class);

    expect($sequences->next())->toBe(1)
        ->and($sequences->next())->toBe(2)
        ->and(orderNumberCounters())->toBe(['default' => 2]);
});

it('rejects a name other than the single sequence', function () {
    expect(fn () => app(SingleOrderNumberSequence::class)->next('2026'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an empty sequence name', function () {
    expect(fn () => app(MultipleOrderNumberSequence::class)->next(''))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps one counter for each sequence name', function () {
    $sequences = app(MultipleOrderNumberSequence::class);

    expect($sequences->next('2026'))->toBe(1)
        ->and($sequences->next('2027'))->toBe(1)
        ->and($sequences->next('2026'))->toBe(2)
        ->and(orderNumberCounters())->toBe(['2026' => 2, '2027' => 1]);
});

it('starts a new sequence name at the configured start', function () {
    config()->set('larasell.order_numbers.start', 500);
    $sequences = app(MultipleOrderNumberSequence::class);

    expect($sequences->next('2026'))->toBe(500)
        ->and($sequences->next('2026'))->toBe(501);
});

it('gives the number back when the claiming transaction rolls back', function () {
    $sequences = app(MultipleOrderNumberSequence::class);
    $sequences->next('2026');

    try {
        DB::transaction(function () use ($sequences): void {
            $sequences->next('2026');

            throw new RuntimeException('Checkout failed.');
        });
    } catch (RuntimeException) {
    }

    expect($sequences->next('2026'))->toBe(2);
});

it('moves a sequence that is below the start up to that start without filling the gap', function () {
    $sequences = app(MultipleOrderNumberSequence::class);
    $sequences->next('2026');

    config()->set('larasell.order_numbers.start', 1000);

    expect($sequences->next('2026'))->toBe(1000)
        ->and($sequences->next('2027'))->toBe(1000)
        ->and($sequences->next('2026'))->toBe(1001);
});

it('continues from the highest issued number when that number is above the start', function () {
    config()->set('larasell.order_numbers.start', 1001);
    $sequences = app(MultipleOrderNumberSequence::class);
    $sequences->next('2026');

    config()->set('larasell.order_numbers.start', 1000);

    expect($sequences->next('2026'))->toBe(1002)
        ->and(orderNumberCounters())->toMatchArray(['2026' => 1002]);
});
