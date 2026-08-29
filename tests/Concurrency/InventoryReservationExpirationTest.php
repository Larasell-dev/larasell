<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Inventory\ReleaseExpiredInventoryForOrder;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Price;

it('keeps payment and expired inventory release consistent when they run concurrently', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not provide row-level FOR UPDATE locking.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for the concurrency test.');
    }

    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

    config()->set('larasell.payments.methods.cash.inventory_reservation_minutes', 60);

    $product = Product::query()->create([
        'slug' => 'concurrent-expiring-reservation',
        'name' => 'Concurrent expiring reservation',
        'price' => Price::of(1000),
        'stock' => 5,
        'allow_backorders' => false,
        'status' => Visibility::Visible,
    ]);
    $cart = Cart::query()->create(['currency' => Currency::EUR]);
    $cart->add($product, 2);

    $order = app(Checkout::class)->create($cart, [
        'customer_email' => 'concurrent@example.com',
        'customer_name' => 'Concurrent Customer',
    ])->order;
    $payment = $order->payments()->sole();
    $order->inventoryReservations()->update(['expires_at' => now()->subMinute()]);

    $defaultConnection = config('database.default');
    config()->set('database.connections.inventory_blocker', config("database.connections.{$defaultConnection}"));
    $blocker = DB::connection('inventory_blocker');
    $blocker->beginTransaction();
    $blocker->table($order->getTable())->where('id', $order->getKey())->lockForUpdate()->first();

    $children = collect(['pay', 'expire'])->map(function (string $operation) use ($order, $payment): array {
        [$parentSocket, $childSocket] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork inventory reservation process.');
        }

        if ($pid === 0) {
            fclose($parentSocket);
            DB::purge();
            fwrite($childSocket, "ready\n");

            try {
                if ($operation === 'pay') {
                    Payment::query()->findOrFail($payment->getKey())->markAsPaid();
                } else {
                    app(ReleaseExpiredInventoryForOrder::class)->handle($order->getKey());
                }

                fwrite($childSocket, "completed\n");
            } catch (Throwable $exception) {
                fwrite($childSocket, 'rejected:'.$exception->getMessage()."\n");
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

    $children->each(function (array $child): void {
        [$pid, $socket] = $child;
        pcntl_waitpid($pid, $status);
        stream_get_contents($socket);
        fclose($socket);

        expect(pcntl_wifexited($status))->toBeTrue()
            ->and(pcntl_wexitstatus($status))->toBe(0);
    });

    DB::purge();

    $order = Order::query()->findOrFail($order->getKey());
    $payment = $order->payments()->sole();
    $reservation = $order->inventoryReservations()->sole();
    $stock = Product::query()->findOrFail($product->getKey())->stock;

    $paidOutcome = $order->status === OrderStatus::Paid
        && $payment->status === PaymentStatus::Succeeded
        && $reservation->status === InventoryReservationStatus::Consumed
        && $stock === 3;
    $expiredOutcome = $order->status === OrderStatus::Cancelled
        && $payment->status === PaymentStatus::Cancelled
        && $reservation->status === InventoryReservationStatus::Released
        && $reservation->release_reason === 'reservation_expired'
        && $stock === 5;

    expect($paidOutcome || $expiredOutcome)->toBeTrue();
});
