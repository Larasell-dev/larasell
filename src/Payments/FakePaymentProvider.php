<?php

namespace Larasell\Larasell\Payments;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Larasell\Larasell\Contracts\PaymentProvider;
use LogicException;

class FakePaymentProvider implements PaymentProvider
{
    public function __construct(private readonly Application $app) {}

    public function pay(PaymentRequest $request): PaymentResult
    {
        if (! $this->app->environment(['local', 'testing'])) {
            throw new LogicException('The fake payment provider may only run in local or testing environments.');
        }

        if (! config('larasell.payments.fake.succeeds', true)) {
            return new PaymentResult(false, failureMessage: 'The fake payment was declined.');
        }

        return new PaymentResult(true, 'fake_'.Str::uuid());
    }
}
