<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;

interface PaymentProvider
{
    /**
     * Initiating the same persisted payment more than once must reuse the same
     * provider-side operation.
     */
    public function initiate(PaymentRequest $request): PaymentResult;
}
