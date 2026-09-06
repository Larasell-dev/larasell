<?php

namespace App\Mail;

class OrderCancelled extends OrderMailable
{
    protected function subjectLine(): string
    {
        return "Order {$this->order->number} has been cancelled";
    }

    protected function markdownView(): string
    {
        return 'mail.orders.cancelled';
    }
}
