<?php

namespace App\Mail;

class OrderFulfilled extends OrderMailable
{
    protected function subjectLine(): string
    {
        return "Order {$this->order->number} has been fulfilled";
    }

    protected function markdownView(): string
    {
        return 'mail.orders.fulfilled';
    }
}
