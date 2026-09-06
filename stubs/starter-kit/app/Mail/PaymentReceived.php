<?php

namespace App\Mail;

class PaymentReceived extends OrderMailable
{
    protected function subjectLine(): string
    {
        return "Payment received for order {$this->order->number}";
    }

    protected function markdownView(): string
    {
        return 'mail.orders.paid';
    }
}
