<?php

namespace Larasell\Larasell;

use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Console\InstallStarterKitCommand;
use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;
use Larasell\Larasell\Contracts\TaxCalculator;
use Larasell\Larasell\Contracts\TaxJurisdictionResolver;
use Larasell\Larasell\Contracts\TaxRateResolver;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\TaxRoundingMode;
use Larasell\Larasell\Inventory\Commands\ReleaseExpiredInventoryCommand;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\OrderNumbers\SequentialOrderNumberGenerator;
use Larasell\Larasell\Payments\PaymentManager;
use Larasell\Larasell\Promotions\Commands\ReleaseExpiredPromotionRedemptionsCommand;
use Larasell\Larasell\Promotions\DefaultPromotionCustomerResolver;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Taxes\CartTaxEstimator;
use Larasell\Larasell\Taxes\TaxRounding;

class LarasellServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/larasell.php', 'larasell');

        $this->app->singleton(ShippingManager::class);
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(PromotionManager::class);
        $this->app->singleton(ModelRegistry::class);
        $this->app->singleton(CartTaxEstimator::class);
        $this->app->singleton(TaxRounding::class, fn ($app) => new TaxRounding(
            TaxRoundingMode::from($app['config']->get('larasell.taxes.rounding', TaxRoundingMode::HalfUp->value))
        ));

        $this->app->bind(OrderNumberGenerator::class, fn ($app) => $app->make(
            $app['config']->get('larasell.order_numbers.generator', SequentialOrderNumberGenerator::class)
        ));
        $this->app->bind(PromotionCustomerResolver::class, fn ($app) => $app->make(
            $app['config']->get('larasell.promotions.customer_resolver', DefaultPromotionCustomerResolver::class)
        ));
        $this->app->bind(TaxCalculator::class, fn ($app) => $app->make(
            $app['config']->get('larasell.taxes.calculator')
        ));
        $this->app->bind(TaxJurisdictionResolver::class, fn ($app) => $app->make(
            $app['config']->get('larasell.taxes.jurisdiction_resolver')
        ));
        $this->app->bind(TaxRateResolver::class, fn ($app) => $app->make(
            $app['config']->get('larasell.taxes.rate_resolver')
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallStarterKitCommand::class,
                ReleaseExpiredInventoryCommand::class,
                ReleaseExpiredPromotionRedemptionsCommand::class,
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
