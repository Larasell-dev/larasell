<?php

namespace App\Providers;

use App\Promotions\SaveTenPercent;
use App\Shipping\ExpressDelivery;
use App\Shipping\StandardDelivery;
use App\Store\Listeners\SendOrderCancelledMail;
use App\Store\Listeners\SendOrderFulfilledMail;
use App\Store\Listeners\SendOrderReceivedMail;
use App\Store\Listeners\SendPaymentReceivedMail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Events\OrderCancelled;
use Larasell\Larasell\Events\OrderFulfilled;
use Larasell\Larasell\Events\OrderPaid;
use Larasell\Larasell\Events\OrderPlaced;
use Larasell\Larasell\Shipping\ShippingManager;

class StoreServiceProvider extends ServiceProvider
{
    public function boot(PromotionManager $promotions, ShippingManager $shipping): void
    {
        $promotions->register(SaveTenPercent::class);
        $shipping->register(StandardDelivery::class);
        $shipping->register(ExpressDelivery::class);

        Event::listen(OrderPlaced::class, SendOrderReceivedMail::class);
        Event::listen(OrderPaid::class, SendPaymentReceivedMail::class);
        Event::listen(OrderFulfilled::class, SendOrderFulfilledMail::class);
        Event::listen(OrderCancelled::class, SendOrderCancelledMail::class);
    }
}
