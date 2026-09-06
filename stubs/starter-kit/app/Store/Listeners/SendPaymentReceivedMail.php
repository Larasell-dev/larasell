<?php

namespace App\Store\Listeners;

use App\Mail\PaymentReceived;
use Illuminate\Support\Facades\Mail;
use Larasell\Larasell\Events\OrderPaid;

class SendPaymentReceivedMail
{
    public function handle(OrderPaid $event): void
    {
        Mail::to($event->order->customer_email)->send(new PaymentReceived($event->order));
    }
}
