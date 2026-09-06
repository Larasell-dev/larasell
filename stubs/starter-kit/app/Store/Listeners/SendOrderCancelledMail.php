<?php

namespace App\Store\Listeners;

use App\Mail\OrderCancelled;
use Illuminate\Support\Facades\Mail;
use Larasell\Larasell\Events\OrderCancelled as OrderCancelledEvent;

class SendOrderCancelledMail
{
    public function handle(OrderCancelledEvent $event): void
    {
        Mail::to($event->order->customer_email)->send(new OrderCancelled($event->order));
    }
}
