<?php

namespace Larasell\Larasell\Inventory\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Inventory\ReleaseExpiredInventoryForOrder;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;

class ReleaseExpiredInventoryCommand extends Command
{
    protected $signature = 'larasell:release-expired-inventory
        {--batch-size=100 : The number of candidate orders to load at a time}';

    protected $description = 'Release expired inventory reservations and cancel their unpaid orders.';

    public function handle(
        ModelRegistry $models,
        ReleaseExpiredInventoryForOrder $releaseExpiredInventory,
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
            ->whereHas('inventoryReservations', function (Builder $query): void {
                $query
                    ->where('status', InventoryReservationStatus::Active->value)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            })
            ->chunkById($batchSize, function (Collection $orders) use ($releaseExpiredInventory, &$released): void {
                /** @var Order $order */
                foreach ($orders as $order) {
                    if ($releaseExpiredInventory->handle($order->getKey())) {
                        $released++;
                    }
                }
            });

        $this->info("Released inventory for {$released} expired orders.");

        return self::SUCCESS;
    }
}
