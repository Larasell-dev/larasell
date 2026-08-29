<?php

namespace Larasell\Larasell\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Models\Refund;

class RefundCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Refund $refund) {}
}
