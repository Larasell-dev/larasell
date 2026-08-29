<?php

namespace Larasell\Larasell;

use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\Inventory\Commands\ReleaseExpiredInventoryCommand;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Payments\PaymentManager;
use Larasell\Larasell\Shipping\ShippingManager;

class LarasellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larasell.php', 'larasell');

        $this->app->singleton(ShippingManager::class);
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(ModelRegistry::class);

        $this->app->bind(OrderNumberGenerator::class, fn ($app) => $app->make(
            $app['config']->get('larasell.order_numbers.generator', SequentialOrderNumberGenerator::class)
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReleaseExpiredInventoryCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/larasell.php' => config_path('larasell.php'),
            ], 'larasell-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'larasell-migrations');
        }
    }
}
