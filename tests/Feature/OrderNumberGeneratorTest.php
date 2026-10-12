<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Price;

it('generates human-readable order numbers from database allocated sequences', function () {
    $generator = app(SequentialOrderNumberGenerator::class);
    $numbers = [
        $generator->generate(),
        $generator->generate(),
        $generator->generate(),
    ];
    $sequences = array_map(fn (string $number): int => (int) substr($number, 3), $numbers);

    expect($numbers)->each->toMatch('/^LS-\d{6,}$/')
        ->and($sequences[1])->toBe($sequences[0] + 1)
        ->and($sequences[2])->toBe($sequences[1] + 1);
});

it('stores the order number returned by a custom generator', function () {
    app()->bind(OrderNumberGenerator::class, fn () => new class implements OrderNumberGenerator
    {
        public function generate(): string
        {
            return 'ORDER-100';
        }
    });

    $order = app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData())->order;

    expect($order->number)->toBe('ORDER-100');
});

it('does not allocate another order number when an idempotency key is retried', function () {
    $generator = new CountingOrderNumberGenerator;
    app()->instance(OrderNumberGenerator::class, $generator);
    $cart = orderNumberCart();

    $first = app(Checkout::class)->create($cart, orderNumberCheckoutData(), idempotencyKey: 'order-number-retry');
    $retried = app(Checkout::class)->create($cart->fresh(), orderNumberCheckoutData(), idempotencyKey: 'order-number-retry');

    expect($generator->calls)->toBe(1)
        ->and($retried->order->is($first->order))->toBeTrue()
        ->and($retried->order->number)->toBe('ORDER-1');
});

it('allocates a new order number when checkout has no idempotency key', function () {
    $generator = new CountingOrderNumberGenerator;
    app()->instance(OrderNumberGenerator::class, $generator);

    $first = app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData())->order;
    $second = app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData())->order;

    expect($generator->calls)->toBe(2)
        ->and($first->number)->toBe('ORDER-1')
        ->and($second->number)->toBe('ORDER-2');
});

it('rejects a duplicate order number', function () {
    app()->bind(OrderNumberGenerator::class, fn () => new class implements OrderNumberGenerator
    {
        public function generate(): string
        {
            return 'ORDER-100';
        }
    });

    app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData());

    expect(fn () => app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData()))
        ->toThrow(QueryException::class)
        ->and(Order::query()->where('number', 'ORDER-100')->count())->toBe(1);
});

it('rejects empty custom order numbers', function () {
    app()->bind(OrderNumberGenerator::class, fn () => new class implements OrderNumberGenerator
    {
        public function generate(): string
        {
            return '';
        }
    });

    expect(fn () => app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData()))
        ->toThrow(RuntimeException::class, 'The order number generator returned an empty order number.');
});

it('keeps existing order numbers when the prefix, suffix, or padding changes', function () {
    $existing = app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData())->order;

    config()->set('larasell.order_numbers.prefix', 'SHOP-');
    config()->set('larasell.order_numbers.suffix', '-EU');
    config()->set('larasell.order_numbers.padding', 3);

    $next = app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData())->order;

    expect($existing->fresh()->number)->toBe('LS-000001')
        ->and($next->number)->toBe('SHOP-002-EU');
});

it('does not pad the sequence when padding is null', function () {
    config()->set('larasell.order_numbers.padding', null);

    expect(app(SequentialOrderNumberGenerator::class)->generate())->toBe('LS-1');
});

it('uses two digits for the tenth order number when padding is null', function () {
    config()->set('larasell.order_numbers.padding', null);
    $generator = app(SequentialOrderNumberGenerator::class);

    foreach (range(1, 9) as $ignored) {
        $generator->generate();
    }

    expect($generator->generate())->toBe('LS-10');
});

it('supports custom prefixes, suffixes, and padding', function () {
    config()->set('larasell.order_numbers.prefix', 'SHOP-');
    config()->set('larasell.order_numbers.suffix', '-EU');
    config()->set('larasell.order_numbers.padding', 3);

    expect(app(SequentialOrderNumberGenerator::class)->generate())->toBe('SHOP-001-EU');
});

it('rejects an invalid order number start', function (mixed $start) {
    config()->set('larasell.order_numbers.start', $start);

    expect(fn () => app(SequentialOrderNumberGenerator::class)->generate())
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero' => 0,
    'negative' => -5,
    'decimal' => 1.5,
    'text' => 'ten',
]);

it('accepts a numeric string padding', function () {
    config()->set('larasell.order_numbers.padding', '3');

    expect(app(SequentialOrderNumberGenerator::class)->generate())->toBe('LS-001');
});

it('rejects a non-numeric order number padding', function (mixed $padding) {
    config()->set('larasell.order_numbers.padding', $padding);

    expect(fn () => app(SequentialOrderNumberGenerator::class)->generate())
        ->toThrow(InvalidArgumentException::class, 'The order number padding must be a positive integer or null.');
})->with([
    'text' => 'six',
    'decimal string' => '1.5',
    'decimal' => 1.5,
    'zero' => 0,
    'zero string' => '0',
    'negative' => -5,
    'negative string' => '-5',
    'array' => [[6]],
]);

it('does not allocate a sequence number when the padding is invalid', function () {
    config()->set('larasell.order_numbers.padding', 'six');

    expect(fn () => app(SequentialOrderNumberGenerator::class)->generate())
        ->toThrow(InvalidArgumentException::class)
        ->and(DB::table('larasell_order_number_sequences')->count())->toBe(0);

    config()->set('larasell.order_numbers.padding', 6);

    expect(app(SequentialOrderNumberGenerator::class)->generate())->toBe('LS-000001');
});

it('keeps existing order numbers when the start changes', function () {
    $existing = app(Checkout::class)->create(orderNumberCart(), orderNumberCheckoutData())->order;

    config()->set('larasell.order_numbers.start', 5000);

    expect($existing->fresh()->number)->toBe('LS-000001');
});

it('starts at a high value without allocating the missing numbers', function () {
    config()->set('larasell.order_numbers.start', 1000);
    $generator = app(SequentialOrderNumberGenerator::class);

    expect($generator->generate())->toBe('LS-001000')
        ->and($generator->generate())->toBe('LS-001001');

    expect(DB::table('larasell_order_number_sequences')->count())->toBe(2)
        ->and(DB::table('larasell_order_number_sequences')->pluck('number')->all())->toBe([1000, 1001]);
});

it('moves the next number up to a higher start without filling the gap', function () {
    $generator = app(SequentialOrderNumberGenerator::class);
    $generator->generate();

    config()->set('larasell.order_numbers.start', 1000);

    expect($generator->generate())->toBe('LS-001000');
    expect(DB::table('larasell_order_number_sequences')->count())->toBe(2)
        ->and(DB::table('larasell_order_number_sequences')->pluck('number')->all())->toBe([1, 1000]);
});

it('continues from the highest issued number when that number is above the start', function () {
    config()->set('larasell.order_numbers.start', 1001);
    $generator = app(SequentialOrderNumberGenerator::class);
    $generator->generate();

    config()->set('larasell.order_numbers.start', 1000);

    expect($generator->generate())->toBe('LS-001002');
    expect(DB::table('larasell_order_number_sequences')->count())->toBe(2);
});

class CountingOrderNumberGenerator implements OrderNumberGenerator
{
    public int $calls = 0;

    public function generate(): string
    {
        $this->calls++;

        return 'ORDER-'.$this->calls;
    }
}

function orderNumberCart(): Cart
{
    $product = Product::query()->create([
        'slug' => 'order-number-product-'.fake()->unique()->numerify('######'),
        'name' => 'Order number product',
        'price' => Price::of(1000),
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product);

    return $cart;
}

/** @return array<string, mixed> */
function orderNumberCheckoutData(): array
{
    return [
        'customer_email' => 'order-number@example.com',
        'customer_name' => 'Order Number',
        'billing_address' => null,
        'shipping_address' => null,
    ];
}
