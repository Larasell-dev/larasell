<?php

namespace Larasell\Larasell;

use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Payments\FakePaymentProvider;

class LarasellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larasell.php', 'larasell');

        $this->app->bind(OrderNumberGenerator::class, fn ($app) => $app->make(
            $app['config']->get('larasell.order_numbers.generator', SequentialOrderNumberGenerator::class)
        ));
        $this->app->bind(PaymentProvider::class, fn ($app) => $app->make(
            $app['config']->get('larasell.payments.provider', FakePaymentProvider::class)
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/larasell.php' => config_path('larasell.php'),
            ], 'larasell-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'larasell-migrations');
        }
    }
}
