<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Contracts\RefundProvider;
use Larasell\Larasell\Refunds\RefundRequest;
use Larasell\Larasell\Refunds\RefundResult;

class OfflinePaymentProvider implements PaymentProvider, RefundProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return PaymentResult::pending();
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return RefundResult::pending();
    }
}
