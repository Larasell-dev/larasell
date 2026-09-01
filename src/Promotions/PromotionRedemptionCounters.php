<?php

namespace Larasell\Larasell\Promotions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Events\PromotionRedemptionRedeemed;
use Larasell\Larasell\Events\PromotionRedemptionReleased;
use Larasell\Larasell\Models\PromotionRedemption;

final readonly class PromotionRedemptionCounters
{
    public function __construct(private ConnectionInterface $database) {}

    public function reserve(string $promotion, string $customer, RedemptionLimits $limits, Carbon $at): void
    {
        if ($limits->global !== null) {
            $counter = $this->lockGlobal($promotion, $at);

            if ($limits->global <= $counter['reserved_count'] + $counter['redeemed_count']) {
                throw new InvalidArgumentException("Promotion [{$promotion}] has reached its redemption limit.");
            }

            $this->database->table('larasell_promotion_redemption_counters')
                ->where('promotion_identifier', $promotion)
                ->update(['reserved_count' => $counter['reserved_count'] + 1, 'updated_at' => $at]);
        }

        if ($limits->customer !== null) {
            $counter = $this->lockCustomer($promotion, $customer, $at);

            if ($limits->customer <= $counter['reserved_count'] + $counter['redeemed_count']) {
                throw new InvalidArgumentException("Promotion [{$promotion}] has reached its customer redemption limit.");
            }

            $this->database->table('larasell_promotion_customer_redemption_counters')
                ->where('promotion_identifier', $promotion)
                ->where('customer_identifier', $customer)
                ->update(['reserved_count' => $counter['reserved_count'] + 1, 'updated_at' => $at]);
        }
    }

    public function redeem(PromotionRedemption $redemption, Carbon $at): void
    {
        $this->transfer($redemption, $at, true);
        $redemption->update(['status' => PromotionRedemptionStatus::Redeemed, 'redeemed_at' => $at]);
        PromotionRedemptionRedeemed::dispatch($redemption);
    }

    public function release(PromotionRedemption $redemption, Carbon $at): void
    {
        $this->transfer($redemption, $at, false);
        $redemption->update(['status' => PromotionRedemptionStatus::Released, 'released_at' => $at]);
        PromotionRedemptionReleased::dispatch($redemption);
    }

    private function transfer(PromotionRedemption $redemption, Carbon $at, bool $redeem): void
    {
        if ($redemption->global_limit !== null) {
            $counter = $this->database->table('larasell_promotion_redemption_counters')
                ->where('promotion_identifier', $redemption->promotion_identifier)
                ->lockForUpdate()
                ->first();
            $counter = $this->counterValues($counter, $redemption->promotion_identifier, true);
            $values = ['reserved_count' => $counter['reserved_count'] - 1, 'updated_at' => $at];
            if ($redeem) {
                $values['redeemed_count'] = $counter['redeemed_count'] + 1;
            }
            $this->database->table('larasell_promotion_redemption_counters')
                ->where('promotion_identifier', $redemption->promotion_identifier)
                ->update($values);
        }

        if ($redemption->customer_limit !== null) {
            $counter = $this->database->table('larasell_promotion_customer_redemption_counters')
                ->where('promotion_identifier', $redemption->promotion_identifier)
                ->where('customer_identifier', $redemption->customer_identifier)
                ->lockForUpdate()
                ->first();
            $counter = $this->counterValues($counter, $redemption->promotion_identifier, true);
            $values = ['reserved_count' => $counter['reserved_count'] - 1, 'updated_at' => $at];
            if ($redeem) {
                $values['redeemed_count'] = $counter['redeemed_count'] + 1;
            }
            $this->database->table('larasell_promotion_customer_redemption_counters')
                ->where('promotion_identifier', $redemption->promotion_identifier)
                ->where('customer_identifier', $redemption->customer_identifier)
                ->update($values);
        }
    }

    /** @return array{reserved_count: int, redeemed_count: int} */
    private function lockGlobal(string $promotion, Carbon $at): array
    {
        $this->database->table('larasell_promotion_redemption_counters')->insertOrIgnore([
            'promotion_identifier' => $promotion,
            'reserved_count' => 0,
            'redeemed_count' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $counter = $this->database->table('larasell_promotion_redemption_counters')
            ->where('promotion_identifier', $promotion)->lockForUpdate()->firstOrFail();

        return $this->counterValues($counter, $promotion);
    }

    /** @return array{reserved_count: int, redeemed_count: int} */
    private function lockCustomer(string $promotion, string $customer, Carbon $at): array
    {
        $this->database->table('larasell_promotion_customer_redemption_counters')->insertOrIgnore([
            'promotion_identifier' => $promotion,
            'customer_identifier' => $customer,
            'reserved_count' => 0,
            'redeemed_count' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $counter = $this->database->table('larasell_promotion_customer_redemption_counters')
            ->where('promotion_identifier', $promotion)
            ->where('customer_identifier', $customer)
            ->lockForUpdate()->firstOrFail();

        return $this->counterValues($counter, $promotion);
    }

    /** @return array{reserved_count: int, redeemed_count: int} */
    private function counterValues(?object $counter, string $promotion, bool $requireReservation = false): array
    {
        if ($counter === null
            || ! isset($counter->reserved_count, $counter->redeemed_count)
            || ! is_numeric($counter->reserved_count)
            || ! is_numeric($counter->redeemed_count)
            || ($requireReservation && (int) $counter->reserved_count < 1)) {
            throw new InvalidArgumentException("Promotion [{$promotion}] has inconsistent redemption capacity.");
        }

        return [
            'reserved_count' => (int) $counter->reserved_count,
            'redeemed_count' => (int) $counter->redeemed_count,
        ];
    }
}
