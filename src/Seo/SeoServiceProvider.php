<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Seo\Commands\RegenerateSeoUrlsCommand;
use Larasell\Larasell\Seo\Contracts\CollisionPolicy;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;
use Larasell\Larasell\Seo\Contracts\Slugifier;

/**
 * The SEO module: public storefront URLs, their history, and what search engines see.
 * Other Larasell code must not depend on it.
 */
class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/larasell/seo.php', 'larasell.seo');

        $this->app->singleton(SeoResources::class);
        $this->app->singleton(SeoModelListener::class);
        $this->app->bind(SeoContextResolver::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.context_resolver', EntryPointResolver::class)
        ));
        $this->app->bind(Slugifier::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.slugifier', DefaultSlugifier::class)
        ));
        $this->app->bind(CollisionPolicy::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.collision_policy', SuffixCollisionPolicy::class)
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/seo');

        foreach (['saved', 'deleting', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($event): void {
                $this->app->make(SeoModelListener::class)->{$event}($payload[0]);
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([RegenerateSeoUrlsCommand::class]);

            $this->publishes([
                __DIR__.'/../../config/larasell/seo.php' => config_path('larasell/seo.php'),
            ], 'larasell-seo-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/seo' => database_path('migrations'),
            ], 'larasell-seo-migrations');
        }
    }
}
