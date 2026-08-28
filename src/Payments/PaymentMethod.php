<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Contracts\PaymentProvider;

final readonly class PaymentMethod
{
    /**
     * @param  class-string<PaymentProvider>  $provider
     */
    public function __construct(
        public string $handle,
        public string $driver,
        public string $provider,
    ) {}
}
