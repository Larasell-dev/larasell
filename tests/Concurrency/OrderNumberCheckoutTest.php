<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('gives concurrent checkouts different order numbers', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not provide row-level FOR UPDATE locking.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for the concurrency test.');
    }

    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

    $product = Product::query()->create([
        'slug' => 'concurrent-order-number',
        'name' => 'Concurrent order number',
        'price' => Price::of(1000),
        'stock' => 2,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $variant = $product->defaultVariant();
    $cartIds = collect([0, 1])->map(function () use ($product): int {
        $cart = Cart::query()->create(['currency' => Currency::EUR]);
        $cart->add($product);

        return (int) $cart->getKey();
    });

    $defaultConnection = config('database.default');
    config()->set('database.connections.order_number_blocker', config("database.connections.{$defaultConnection}"));
    $blocker = DB::connection('order_number_blocker');
    $blocker->beginTransaction();
    $blocker->table($variant->getTable())->where('id', $variant->getKey())->lockForUpdate()->first();

    $children = $cartIds->map(function (int $cartId, int $index): array {
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork checkout process.');
        }

        if ($pid === 0) {
            fclose($parentSocket);
            DB::purge();
            fwrite($childSocket, "ready\n");

            try {
                $result = app(Checkout::class)->create(Cart::query()->findOrFail($cartId), [
                    'customer_email' => "buyer{$index}@example.com",
                    'customer_name' => "Buyer {$index}",
                ]);
                fwrite($childSocket, 'succeeded:'.$result->order->number."\n");
            } catch (Throwable $exception) {
                fwrite($childSocket, 'failed:'.$exception->getMessage()."\n");
            }

            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);
        stream_set_timeout($parentSocket, 10);

        return [$pid, $parentSocket];
    });

    $children->each(function (array $child): void {
        expect(fgets($child[1]))->toBe("ready\n");
    });
    usleep(200_000);
    $blocker->commit();

    $results = $children->map(function (array $child): string {
        [$pid, $socket] = $child;
        pcntl_waitpid($pid, $status);
        $result = trim((string) stream_get_contents($socket));
        fclose($socket);

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0);

        return $result;
    });

    DB::purge();

    $numbers = Order::query()->pluck('number');

    expect($results->every(fn (string $result): bool => str_starts_with($result, 'succeeded:')))->toBeTrue()
        ->and($numbers)->toHaveCount(2)
        ->and($numbers->unique())->toHaveCount(2);
});
