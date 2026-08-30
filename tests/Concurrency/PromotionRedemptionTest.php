<?php

use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Checkout\Checkout;
use Larasell\Larasell\Contracts\Promotions\HasRedemptionLimit;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\Models\PromotionRedemption;
use Larasell\Larasell\Price;

final class FinalRedemptionPromotion implements HasRedemptionLimit, Promotion
{
    public function limit(): int
    {
        return 1;
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        return new DiscountResult(
            'final-redemption',
            'Final redemption',
            $context->fixedAmountOff(Price::of(100)),
        );
    }
}

it('allows only one concurrent checkout to reserve the final promotion redemption', function () {
    if (DB::getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite does not provide row-level FOR UPDATE locking.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('The pcntl extension is required for the concurrency test.');
    }

    $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    app(PromotionManager::class)->register(FinalRedemptionPromotion::class);

    $product = Product::query()->create([
        'slug' => 'final-promotion-redemption',
        'name' => 'Final promotion redemption',
        'price' => Price::of(1000),
        'stock' => null,
        'allow_backorders' => true,
        'status' => Visibility::Visible,
    ]);
    $cartIds = collect([1, 2])->map(function () use ($product): int {
        $cart = Cart::query()->create(['currency' => Currency::EUR]);
        $cart->add($product);

        return (int) $cart->getKey();
    });

    DB::table('larasell_promotion_redemption_counters')->insert([
        'promotion_identifier' => 'final-redemption',
        'reserved_count' => 0,
        'redeemed_count' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $defaultConnection = config('database.default');
    config()->set('database.connections.promotion_blocker', config("database.connections.{$defaultConnection}"));
    $blocker = DB::connection('promotion_blocker');
    $blocker->beginTransaction();
    $blocker->table('larasell_promotion_redemption_counters')
        ->where('promotion_identifier', 'final-redemption')
        ->lockForUpdate()
        ->first();

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
                app(Checkout::class)->create(Cart::query()->findOrFail($cartId), [
                    'customer_email' => "promotion-buyer{$index}@example.com",
                    'customer_name' => "Promotion Buyer {$index}",
                ]);
                fwrite($childSocket, "succeeded\n");
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

    $children->each(fn (array $child) => expect(fgets($child[1]))->toBe("ready\n"));
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

    expect($results->filter(fn (string $result): bool => $result === 'succeeded'))->toHaveCount(1)
        ->and($results->filter(fn (string $result): bool => str_starts_with(
            $result,
            'failed:Promotion [final-redemption] has reached its redemption limit.',
        )))->toHaveCount(1)
        ->and(PromotionRedemption::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(1)
        ->and(DB::table('larasell_promotion_redemption_counters')->value('reserved_count'))->toBe(1);
});
