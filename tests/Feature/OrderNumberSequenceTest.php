<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\OrderNumbers\MultipleOrderNumberSequence;
use Larasell\Larasell\OrderNumbers\SingleOrderNumberSequence;

it('allocates the default sequence when no name is given', function () {
    $sequences = app(SingleOrderNumberSequence::class);

    expect($sequences->next())->toBe(1)
        ->and($sequences->next())->toBe(2);

    expect(DB::table('larasell_order_number_sequences')->pluck('name')->all())->toBe(['default', 'default']);
});

it('rejects a name other than the single sequence', function () {
    expect(fn () => app(SingleOrderNumberSequence::class)->next('2026'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an empty sequence name', function () {
    expect(fn () => app(MultipleOrderNumberSequence::class)->next(''))
        ->toThrow(InvalidArgumentException::class);
});

it('allocates independent numbers for each sequence name', function () {
    $sequences = app(MultipleOrderNumberSequence::class);

    expect($sequences->next('2026'))->toBe(1)
        ->and($sequences->next('2027'))->toBe(1)
        ->and($sequences->next('2026'))->toBe(2);

    expect(DB::table('larasell_order_number_sequences')->count())->toBe(3);
});

it('moves a sequence that is below the start up to that start without filling the gap', function () {
    $sequences = app(MultipleOrderNumberSequence::class);
    $sequences->next('2026');

    config()->set('larasell.order_numbers.start', 1000);

    expect($sequences->next('2026'))->toBe(1000)
        ->and($sequences->next('2027'))->toBe(1000);

    expect(DB::table('larasell_order_number_sequences')->where('name', '2026')->pluck('number')->all())->toBe([1, 1000]);
});

it('continues from the highest issued number when that number is above the start', function () {
    config()->set('larasell.order_numbers.start', 1001);
    $sequences = app(MultipleOrderNumberSequence::class);
    $sequences->next('2026');

    config()->set('larasell.order_numbers.start', 1000);

    expect($sequences->next('2026'))->toBe(1002);
    expect(DB::table('larasell_order_number_sequences')->where('name', '2026')->count())->toBe(2);
});
