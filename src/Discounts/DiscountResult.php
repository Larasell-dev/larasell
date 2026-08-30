<?php

namespace Larasell\Larasell\Discounts;

use InvalidArgumentException;
use Larasell\Larasell\Price;
use Larasell\Larasell\Promotions\RedemptionLimits;

final readonly class DiscountResult
{
    /** @var array<int, DiscountAllocation> */
    public array $allocations;

    /** @param array<int, DiscountAllocation> $allocations */
    public function __construct(
        public string $identifier,
        public string $name,
        array $allocations,
        public ?string $code = null,
        public ?RedemptionLimits $redemptionLimits = null,
    ) {
        if (trim($identifier) === '') {
            throw new InvalidArgumentException('A discount identifier is required.');
        }

        if (trim($name) === '') {
            throw new InvalidArgumentException('A discount name is required.');
        }

        if ($code !== null && trim($code) === '') {
            throw new InvalidArgumentException('A discount code cannot be empty.');
        }

        $targets = [];

        foreach ($allocations as $allocation) {
            if (! $allocation instanceof DiscountAllocation) {
                throw new InvalidArgumentException('Discount allocations must be DiscountAllocation instances.');
            }

            if (isset($targets[$allocation->target])) {
                throw new InvalidArgumentException('A discount result may only contain one allocation per target.');
            }

            $targets[$allocation->target] = true;
        }

        $this->allocations = array_values($allocations);
    }

    public function total(): Price
    {
        return array_reduce(
            $this->allocations,
            fn (Price $total, DiscountAllocation $allocation): Price => $total->add($allocation->amount),
            Price::of(0),
        );
    }
}
