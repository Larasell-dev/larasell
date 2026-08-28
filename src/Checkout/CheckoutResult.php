<?php

namespace Larasell\Larasell\Checkout;

use Illuminate\Http\RedirectResponse;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Payments\PaymentAction;
use Larasell\Larasell\Payments\RedirectPaymentAction;
use LogicException;

final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public Payment $payment,
        public ?PaymentAction $action = null,
    ) {}

    public function requiresRedirect(): bool
    {
        return $this->action instanceof RedirectPaymentAction;
    }

    public function redirect(): RedirectResponse
    {
        if (! $this->action instanceof RedirectPaymentAction) {
            throw new LogicException('This checkout does not require a redirect.');
        }

        return redirect()->away($this->action->url);
    }
}
