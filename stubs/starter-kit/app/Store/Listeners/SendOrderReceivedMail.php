<?php

namespace App\Store\Listeners;

use App\Mail\OrderReceived;
use Illuminate\Support\Facades\Mail;
use Larasell\Larasell\Events\OrderPlaced;

class SendOrderReceivedMail
{
    public function handle(OrderPlaced $event): void
    {
        Mail::to($event->order->customer_email)->send(new OrderReceived($event->order));
    }
}
