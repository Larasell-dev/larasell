<?php

namespace App\Providers;

use App\Promotions\SaveTenPercent;
use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Discounts\PromotionManager;

class StoreServiceProvider extends ServiceProvider
{
    public function boot(PromotionManager $promotions): void
    {
        $promotions->register(SaveTenPercent::class);
    }
}
