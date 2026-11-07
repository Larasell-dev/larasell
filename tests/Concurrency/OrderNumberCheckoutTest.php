<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
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
        'name' => 'Concurrent order number',
        'price' => Price::of(1000),
        'stock' => 2,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $variant = $product->defaultVariant();
    $cartIds = collect([0, 1])->map(function () use ($product): int {
        $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
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

it('numbers many concurrent checkouts without deadlocks or gaps', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite serialises all writers, so checkouts cannot race.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for the concurrency test.');
    }

    $workers = 8;
    $checkoutsPerWorker = 25;

    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

    // Every cart buys its own product, so checkouts only contend for the order number.
    $cartIds = collect(range(1, $workers * $checkoutsPerWorker))->map(function (int $index): int {
        $product = Product::query()->create([
            'name' => "Concurrent product {$index}",
            'price' => Price::of(1000),
            'stock' => 1,
            'allow_backorders' => false,
            'status' => Visibility::Visible,
        ]);
        $cart = Cart::createIn(shopContext(), ['currency' => Currency::EUR]);
        $cart->add($product);

        return (int) $cart->getKey();
    })->chunk($checkoutsPerWorker)->map->values();

    $deadlocksBefore = concurrencyDeadlockCount();
    DB::disconnect();

    $children = $cartIds->values()->map(function ($workerCartIds, int $worker): array {
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork checkout process.');
        }

        if ($pid === 0) {
            fclose($parentSocket);
            DB::purge();
            fwrite($childSocket, "ready\n");
            fgets($childSocket);

            foreach ($workerCartIds as $cartId) {
                try {
                    $result = app(Checkout::class)->create(Cart::query()->findOrFail($cartId), [
                        'customer_email' => "buyer{$worker}@example.com",
                        'customer_name' => "Buyer {$worker}",
                    ]);
                    fwrite($childSocket, 'succeeded:'.$result->order->number."\n");
                } catch (Throwable $exception) {
                    fwrite($childSocket, 'failed:'.str_replace("\n", ' ', $exception->getMessage())."\n");
                }
            }

            fclose($childSocket);
            exit(0);
        }

        fclose($childSocket);
        stream_set_timeout($parentSocket, 60);

        return [$pid, $parentSocket];
    });

    $children->each(fn (array $child) => expect(fgets($child[1]))->toBe("ready\n"));
    $children->each(fn (array $child) => fwrite($child[1], "go\n"));

    $results = $children->flatMap(function (array $child): array {
        [$pid, $socket] = $child;
        $lines = explode("\n", trim((string) stream_get_contents($socket)));
        fclose($socket);
        pcntl_waitpid($pid, $status);

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0);

        return $lines;
    });

    DB::purge();

    $total = $workers * $checkoutsPerWorker;
    $expectedNumbers = collect(range(1, $total))->map(fn (int $number): string => sprintf('LS-%06d', $number))->all();

    expect($results->reject(fn (string $result): bool => str_starts_with($result, 'succeeded:'))->values()->all())->toBe([])
        ->and($results)->toHaveCount($total)
        ->and(Order::query()->orderBy('number')->pluck('number')->all())->toBe($expectedNumbers)
        ->and(concurrencyDeadlockCount())->toBe($deadlocksBefore);
});

/** The number of deadlocks the database server has detected so far. */
function concurrencyDeadlockCount(): int
{
    if (DB::getDriverName() === 'pgsql') {
        DB::statement('SELECT pg_stat_clear_snapshot()');

        return (int) DB::scalar('SELECT deadlocks FROM pg_stat_database WHERE datname = current_database()');
    }

    return (int) DB::scalar("SELECT `count` FROM information_schema.INNODB_METRICS WHERE name = 'lock_deadlocks'");
}
