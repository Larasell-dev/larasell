<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;

interface PaymentProvider
{
    public function initiate(PaymentRequest $request): PaymentResult;
}
