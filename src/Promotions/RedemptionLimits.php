<?php

namespace Larasell\Larasell\Promotions;

use InvalidArgumentException;

final readonly class RedemptionLimits
{
    public function __construct(
        public ?int $global,
        public ?int $customer,
    ) {}

    /** @param int|array<string, mixed> $limits */
    public static function from(int|array $limits): self
    {
        if (is_int($limits)) {
            self::assertPositive('global', $limits);

            return new self($limits, null);
        }

        $unknown = array_diff(array_keys($limits), ['global', 'customer']);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown promotion redemption limit ['.reset($unknown).'].');
        }

        $global = $limits['global'] ?? null;
        $customer = $limits['customer'] ?? null;

        if ($global === null && $customer === null) {
            throw new InvalidArgumentException('A global or customer promotion redemption limit is required.');
        }

        self::assertPositive('global', $global);
        self::assertPositive('customer', $customer);

        return new self($global, $customer);
    }

    private static function assertPositive(string $name, mixed $limit): void
    {
        if ($limit !== null && (! is_int($limit) || $limit < 1)) {
            throw new InvalidArgumentException("The {$name} promotion redemption limit must be a positive integer.");
        }
    }
}
