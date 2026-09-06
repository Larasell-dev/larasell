<?php

namespace App\Mail;

class OrderReceived extends OrderMailable
{
    protected function subjectLine(): string
    {
        return "Order {$this->order->number} received";
    }

    protected function markdownView(): string
    {
        return 'mail.orders.received';
    }
}
