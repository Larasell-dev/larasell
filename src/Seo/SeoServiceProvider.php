<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Catalog\Events\CategoryCreated;
use Larasell\Larasell\Catalog\Events\CategoryDeleted;
use Larasell\Larasell\Catalog\Events\CategoryMoved;
use Larasell\Larasell\Catalog\Events\CategoryUpdated;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Seo\Catalog\CatalogUrls;
use Larasell\Larasell\Seo\Commands\RegenerateSeoUrlsCommand;
use Larasell\Larasell\Seo\Contracts\CollisionPolicy;
use Larasell\Larasell\Seo\Contracts\HiddenPagePolicy;
use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Larasell\Larasell\Seo\Contracts\Slugifier;
use Larasell\Larasell\Seo\Events\SeoUrlChanged;
use Larasell\Larasell\Seo\Http\Middleware\ResolveStorefront;
use Larasell\Larasell\Seo\Jobs\BackfillSeoUrls;
use Larasell\Larasell\Storefront\Events\EntryPointCreated;
use Larasell\Larasell\Storefront\StorefrontRoutes;

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
        // Scoped: preloaded paths must not outlive a request or job, e.g. under Octane or in queue workers.
        $this->app->scoped(SeoPages::class);
        $this->app->bind(Slugifier::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.slugifier', DefaultSlugifier::class)
        ));
        $this->app->bind(PathNormalizer::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.path_normalizer', DefaultPathNormalizer::class)
        ));
        $this->app->bind(HiddenPagePolicy::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.hidden_policy', NotFoundForHiddenPages::class)
        ));
        $this->app->bind(CollisionPolicy::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.collision_policy', SuffixCollisionPolicy::class)
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/seo');

        SeoRoutes::register();

        Event::listen(SeoUrlChanged::class, function (SeoUrlChanged $event): void {
            $this->app->make(SeoPages::class)->forget($event->resourceType, $event->resourceId, $event->context);
        });

        // Pages must be bound before implicit binding looks up {product} by its ID.
        $this->callAfterResolving(Kernel::class, function (Kernel $kernel): void {
            if ($kernel instanceof HttpKernel) {
                $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, ResolveStorefront::class);
            }
        });
        $this->callAfterResolving('router', function (Router $router): void {
            $router->pushMiddlewareToGroup(StorefrontRoutes::MIDDLEWARE_GROUP, ResolveStorefront::class);
        });

        // A new channel or language gets its URLs from a queued backfill; its pages answer 404 meanwhile.
        Event::listen(EntryPointCreated::class, fn (EntryPointCreated $event) => BackfillSeoUrls::dispatch($event->channelId, $event->locale->value));

        // Sync listeners, so the URLs are written inside the catalog action's transaction.
        Event::listen([ProductCreated::class, ProductUpdated::class], [CatalogUrls::class, 'productChanged']);
        Event::listen(ProductDeleted::class, [CatalogUrls::class, 'productDeleted']);
        Event::listen([CategoryCreated::class, CategoryUpdated::class, CategoryMoved::class], [CatalogUrls::class, 'categoryChanged']);
        Event::listen(CategoryDeleted::class, [CatalogUrls::class, 'categoryDeleted']);

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
