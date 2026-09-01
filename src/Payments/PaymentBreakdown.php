<?php

namespace Larasell\Larasell\Payments;

use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Price;

final readonly class PaymentBreakdown implements JsonSerializable
{
    /** @param list<PaymentLine> $lines */
    public function __construct(
        public array $lines,
        public ?PaymentLine $shipping,
        public Price $total,
    ) {
        if ($lines === []) {
            throw new InvalidArgumentException('A payment breakdown requires at least one product line.');
        }

        $identifiers = [];
        $calculatedTotal = Price::of(0);

        foreach ([...$lines, ...($shipping === null ? [] : [$shipping])] as $line) {
            if (isset($identifiers[$line->identifier])) {
                throw new InvalidArgumentException("Payment line identifier [{$line->identifier}] must be unique.");
            }

            $identifiers[$line->identifier] = true;
            $calculatedTotal = $calculatedTotal->add($line->amount);
        }

        if ($calculatedTotal->amount() !== $total->amount()) {
            throw new InvalidArgumentException(
                "Payment breakdown total [{$calculatedTotal->amount()}] does not match payment amount [{$total->amount()}]."
            );
        }
    }

    /** @return array{lines: list<array<string, mixed>>, shipping: array<string, mixed>|null, total: array{amount: string}} */
    public function toArray(): array
    {
        return [
            'lines' => array_map(fn (PaymentLine $line): array => $line->toArray(), $this->lines),
            'shipping' => $this->shipping?->toArray(),
            'total' => $this->total->toArray(),
        ];
    }

    /** @return array{lines: list<array<string, mixed>>, shipping: array<string, mixed>|null, total: array{amount: string}} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
