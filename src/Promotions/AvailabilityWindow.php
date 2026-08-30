<?php

namespace Larasell\Larasell\Promotions;

use Carbon\CarbonInterface;
use InvalidArgumentException;

final readonly class AvailabilityWindow
{
    public function __construct(
        public ?CarbonInterface $startsAt,
        public ?CarbonInterface $endsAt,
    ) {}

    /** @param array<string, mixed> $window */
    public static function from(array $window): self
    {
        $unknown = array_diff(array_keys($window), ['starts_at', 'ends_at']);

        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown promotion availability window key ['.reset($unknown).'].');
        }

        $startsAt = $window['starts_at'] ?? null;
        $endsAt = $window['ends_at'] ?? null;

        if ($startsAt === null && $endsAt === null) {
            throw new InvalidArgumentException('A promotion availability window requires a start or end.');
        }

        if ($startsAt !== null && ! $startsAt instanceof CarbonInterface) {
            throw new InvalidArgumentException('The promotion availability start must implement '.CarbonInterface::class.'.');
        }

        if ($endsAt !== null && ! $endsAt instanceof CarbonInterface) {
            throw new InvalidArgumentException('The promotion availability end must implement '.CarbonInterface::class.'.');
        }

        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThan($startsAt)) {
            throw new InvalidArgumentException('The promotion availability end must not be before its start.');
        }

        return new self($startsAt, $endsAt);
    }

    public function contains(CarbonInterface $time): bool
    {
        return ! ($this->startsAt?->greaterThan($time) ?? false)
            && ! ($this->endsAt?->lessThan($time) ?? false);
    }
}
