<?php

namespace Larasell\Larasell\Cart\Exceptions;

final class MissingRequiredShippingAddressException extends CartCheckoutException
{
    public function __construct(
        public readonly string $shippingOption,
    ) {
        parent::__construct('A shipping_address is required for the selected shipping option.');
    }

    public function reason(): string
    {
        return 'missing_required_shipping_address';
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
