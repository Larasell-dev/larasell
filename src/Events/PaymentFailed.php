<?php

namespace Larasell\Larasell\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Larasell\Larasell\Models\Payment;

class PaymentFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
