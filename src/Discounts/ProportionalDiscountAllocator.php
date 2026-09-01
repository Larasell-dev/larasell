<?php

namespace Larasell\Larasell\Discounts;

use InvalidArgumentException;
use Larasell\Larasell\Price;

final class ProportionalDiscountAllocator
{
    /**
     * @param  array<string, mixed>  $eligibleAmounts
     * @return array<int, DiscountAllocation>
     */
    public function allocate(Price $discount, array $eligibleAmounts): array
    {
        if (bccomp($discount->amount(), '0', 0) === -1) {
            throw new InvalidArgumentException('A discount amount cannot be negative.');
        }

        /** @var array<string, numeric-string> $eligible */
        $eligible = [];
        /** @var numeric-string $eligibleTotal */
        $eligibleTotal = '0';

        foreach ($eligibleAmounts as $target => $amount) {
            if (! $amount instanceof Price) {
                throw new InvalidArgumentException('Eligible amounts must be Price instances.');
            }

            if (bccomp($amount->amount(), '0', 0) === -1) {
                throw new InvalidArgumentException('An eligible amount cannot be negative.');
            }

            if (! $amount->isPositive()) {
                continue;
            }

            $target = (string) $target;

            if (trim($target) === '') {
                throw new InvalidArgumentException('An eligible target is required.');
            }

            $eligible[$target] = $amount->amount();
            $eligibleTotal = bcadd($eligibleTotal, $amount->amount(), 0);
        }

        if ($discount->amount() === '0' || $eligibleTotal === '0') {
            return [];
        }

        ksort($eligible, SORT_STRING);
        $discountAmount = bccomp($discount->amount(), $eligibleTotal, 0) === 1
            ? $eligibleTotal
            : $discount->amount();
        /** @var numeric-string $allocatedTotal */
        $allocatedTotal = '0';
        /** @var array<string, array{amount: numeric-string, remainder: numeric-string}> $shares */
        $shares = [];

        foreach ($eligible as $target => $eligibleAmount) {
            $weightedAmount = bcmul($discountAmount, $eligibleAmount, 0);
            $amount = bcdiv($weightedAmount, $eligibleTotal, 0);
            $shares[$target] = [
                'amount' => $amount,
                'remainder' => bcmod($weightedAmount, $eligibleTotal),
            ];
            $allocatedTotal = bcadd($allocatedTotal, $amount, 0);
        }

        $remainderUnits = (int) bcsub($discountAmount, $allocatedTotal, 0);
        $remainderOrder = array_keys($shares);
        usort($remainderOrder, function (string $left, string $right) use ($shares): int {
            $remainderComparison = bccomp($shares[$right]['remainder'], $shares[$left]['remainder'], 0);

            return $remainderComparison !== 0 ? $remainderComparison : strcmp($left, $right);
        });

        for ($index = 0; $index < $remainderUnits; $index++) {
            $target = $remainderOrder[$index];
            $shares[$target]['amount'] = bcadd($shares[$target]['amount'], '1', 0);
        }

        $allocations = [];

        foreach ($shares as $target => $share) {
            if ($share['amount'] !== '0') {
                $allocations[] = new DiscountAllocation($target, Price::of($share['amount']));
            }
        }

        return $allocations;
    }
}
