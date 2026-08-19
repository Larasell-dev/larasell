<?php

use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;

it('generates human-readable order numbers from database allocated sequences', function () {
    $factory = app(OrderNumberFactory::class);

    expect($factory->generate())->toBe('LS-000001')
        ->and($factory->generate())->toBe('LS-000002')
        ->and($factory->generate())->toBe('LS-000003');
});

it('allows the order number format to be customized', function () {
    app()->bind(OrderNumberGenerator::class, fn () => new class implements OrderNumberGenerator
    {
        public function generate(int $sequence): string
        {
            return "ORDER-$sequence";
        }
    });

    expect(app(OrderNumberFactory::class)->generate())->toBe('ORDER-1');
});

it('rejects empty custom order numbers', function () {
    app()->bind(OrderNumberGenerator::class, fn () => new class implements OrderNumberGenerator
    {
        public function generate(int $sequence): string
        {
            return '';
        }
    });

    expect(fn () => app(OrderNumberFactory::class)->generate())
        ->toThrow(RuntimeException::class, 'The order number generator returned an empty order number.');
});

it('supports custom prefixes and padding', function () {
    config()->set('larasell.order_numbers.prefix', 'SHOP-');
    config()->set('larasell.order_numbers.padding', 3);

    expect(app(OrderNumberFactory::class)->generate())->toBe('SHOP-001');
});
