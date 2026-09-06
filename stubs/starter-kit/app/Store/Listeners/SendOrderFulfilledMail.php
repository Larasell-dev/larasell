<?php

namespace App\Store\Listeners;

use App\Mail\OrderFulfilled;
use Illuminate\Support\Facades\Mail;
use Larasell\Larasell\Events\OrderFulfilled as OrderFulfilledEvent;

class SendOrderFulfilledMail
{
    public function handle(OrderFulfilledEvent $event): void
    {
        Mail::to($event->order->customer_email)->send(new OrderFulfilled($event->order));
    }
}
