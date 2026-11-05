<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;

/**
 * The SEO module: public storefront URLs, their history, and what search engines see.
 * Other Larasell code must not depend on it.
 */
class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/larasell/seo.php', 'larasell.seo');

        $this->app->bind(SeoContextResolver::class, fn ($app) => $app->make(
            $app['config']->get('larasell.seo.context_resolver', EntryPointResolver::class)
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/larasell/seo.php' => config_path('larasell/seo.php'),
            ], 'larasell-seo-config');
        }
    }
}
