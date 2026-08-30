<?php

namespace Larasell\Larasell\Promotions\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Promotions\ReleaseExpiredPromotionRedemptionsForOrder;

class ReleaseExpiredPromotionRedemptionsCommand extends Command
{
    protected $signature = 'larasell:release-expired-promotions
        {--batch-size=100 : The number of candidate orders to load at a time}';

    protected $description = 'Release expired promotion redemptions and cancel their unpaid orders.';

    public function handle(
        ModelRegistry $models,
        ReleaseExpiredPromotionRedemptionsForOrder $releaseExpiredPromotions,
    ): int {
        $batchSize = filter_var($this->option('batch-size'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($batchSize === false) {
            $this->error('The batch size must be a positive integer.');

            return self::FAILURE;
        }

        $released = 0;

        $models->order->query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereHas('promotionRedemptions', function (Builder $query): void {
                $query
                    ->where('status', PromotionRedemptionStatus::Reserved->value)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            })
            ->chunkById($batchSize, function (Collection $orders) use ($releaseExpiredPromotions, &$released): void {
                /** @var Order $order */
                foreach ($orders as $order) {
                    if ($releaseExpiredPromotions->handle($order->getKey())) {
                        $released++;
                    }
                }
            });

        $noun = $released === 1 ? 'order' : 'orders';
        $this->info("Released promotions for {$released} expired {$noun}.");

        return self::SUCCESS;
    }
}
