<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Contracts\PaymentProvider;

class OfflinePaymentProvider implements PaymentProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return PaymentResult::pending();
    }
}
