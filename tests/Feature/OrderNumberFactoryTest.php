<?php

use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;

it('generates human-readable order numbers from database allocated sequences', function () {
    $factory = app(OrderNumberFactory::class);
    $numbers = [
        $factory->generate(),
        $factory->generate(),
        $factory->generate(),
    ];
    $sequences = array_map(fn (string $number): int => (int) substr($number, 3), $numbers);

    expect($numbers)->each->toMatch('/^LS-\d{6,}$/')
        ->and($sequences[1])->toBe($sequences[0] + 1)
        ->and($sequences[2])->toBe($sequences[1] + 1);
});

it('allows the order number format to be customized', function () {
    $generator = new class implements OrderNumberGenerator
    {
        public ?int $sequence = null;

        public function generate(int $sequence): string
        {
            $this->sequence = $sequence;

            return "ORDER-$sequence";
        }
    };
    app()->instance(OrderNumberGenerator::class, $generator);

    expect(app(OrderNumberFactory::class)->generate())->toBe("ORDER-{$generator->sequence}");
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

    expect(app(OrderNumberFactory::class)->generate())->toMatch('/^SHOP-\d{3,}$/');
});
