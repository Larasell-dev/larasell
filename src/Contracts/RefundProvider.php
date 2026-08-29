<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Refunds\RefundRequest;
use Larasell\Larasell\Refunds\RefundResult;

interface RefundProvider
{
    public function refund(RefundRequest $request): RefundResult;
}
