<?php

namespace Larasell\Larasell\Payments;

final readonly class RedirectPaymentAction implements PaymentAction
{
    public function __construct(public string $url) {}
}
