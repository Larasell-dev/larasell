<?php

namespace Larasell\Larasell\Exceptions\Cart;

final class StaleSelectedShippingOptionException extends CartException
{
    public function __construct(
        public readonly string $shippingOption,
    ) {
        parent::__construct("Selected shipping option [{$shippingOption}] is no longer available for this cart.");
    }

    public function reason(): string
    {
        return 'stale_selected_shipping_option';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reason' => $this->reason(),
            'shipping_option' => $this->shippingOption,
        ];
    }
}
