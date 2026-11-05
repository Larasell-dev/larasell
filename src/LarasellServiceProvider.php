<?php

namespace Larasell\Larasell;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Larasell\Larasell\Carts\CartMerger;
use Larasell\Larasell\Carts\CartMergeStrategy;
use Larasell\Larasell\Carts\Strategies\CombineQuantities;
use Larasell\Larasell\Contracts\OrderNumberGenerator;
use Larasell\Larasell\Contracts\PlaceholderGenerator;
use Larasell\Larasell\Contracts\PlanResolver;
use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;
use Larasell\Larasell\Contracts\TaxCalculator;
use Larasell\Larasell\Contracts\TaxJurisdictionResolver;
use Larasell\Larasell\Contracts\TaxRateResolver;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\TaxRoundingMode;
use Larasell\Larasell\Images\Commands\RefreshPlaceholdersCommand;
use Larasell\Larasell\Images\NullPlaceholderGenerator;
use Larasell\Larasell\Inventory\Commands\ReleaseExpiredInventoryCommand;
use Larasell\Larasell\Licensing\Commands\ActivateLicenseCommand;
use Larasell\Larasell\Licensing\Commands\RenewLicenseCommand;
use Larasell\Larasell\Licensing\Entitlements;
use Larasell\Larasell\Licensing\EnvPlanResolver;
use Larasell\Larasell\Licensing\LeasePlanResolver;
use Larasell\Larasell\Licensing\License;
use Larasell\Larasell\OrderNumbers\Commands\MakeOrderNumberGeneratorCommand;
use Larasell\Larasell\OrderNumbers\Commands\MakeOrderNumberSequenceCommand;
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
        $this->mergeConfigFrom(__DIR__.'/../config/larasell/locale.php', 'larasell.locale');

        $this->app->singleton(ShippingManager::class);
        $this->app->singleton(PaymentManager::class);
        $this->app->singleton(PromotionManager::class);
        $this->app->singleton(CartTaxEstimator::class);
        $this->app->singleton(CartMerger::class);
        $this->app->singleton(License::class);
        $this->app->singleton(Entitlements::class);
        $this->app->singleton(TaxRounding::class, fn ($app) => new TaxRounding(
            TaxRoundingMode::from($app['config']->get('larasell.taxes.rounding', TaxRoundingMode::HalfUp->value))
        ));

        $this->app->bind(CartMergeStrategy::class, fn ($app) => $app->make(
            $app['config']->get('larasell.carts.merge_strategy', CombineQuantities::class)
        ));
        $this->app->bind(OrderNumberGenerator::class, fn ($app) => $app->make(
            $app['config']->get('larasell.order_numbers.generator', SequentialOrderNumberGenerator::class)
        ));
        $this->app->bind(PlaceholderGenerator::class, fn ($app) => $app->make(
            $app['config']->get('larasell.images.placeholder', NullPlaceholderGenerator::class)
        ));
        // Production always uses the lease, so LARASELL_LICENSE_DRIVER=env cannot unlock Pro on a live shop.
        $this->app->bind(PlanResolver::class, fn ($app) => $app->make(
            $app->isProduction() ? LeasePlanResolver::class : match ($app['config']->get('larasell.licensing.driver', 'env')) {
                'env' => EnvPlanResolver::class,
                'lease' => LeasePlanResolver::class,
                default => throw new InvalidArgumentException('LARASELL_LICENSE_DRIVER must be "env" or "lease".'),
            }
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
                ActivateLicenseCommand::class,
                MakeOrderNumberGeneratorCommand::class,
                MakeOrderNumberSequenceCommand::class,
                RefreshPlaceholdersCommand::class,
                ReleaseExpiredInventoryCommand::class,
                ReleaseExpiredPromotionRedemptionsCommand::class,
                RenewLicenseCommand::class,
            ]);

            // Daily, well within the 14 days a lease lasts.
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command(RenewLicenseCommand::class)->daily()
                    ->when(fn (): bool => $this->app->make(PlanResolver::class) instanceof LeasePlanResolver);
            });

            $this->publishes([
                __DIR__.'/../config/larasell.php' => config_path('larasell.php'),
                __DIR__.'/../config/larasell/locale.php' => config_path('larasell/locale.php'),
            ], 'larasell-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'larasell-migrations');
        }
    }
}
