<?php

namespace Larasell\Larasell\Storefront;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Larasell\Larasell\SalesChannels\Events\SalesChannelUpdated;
use Larasell\Larasell\SalesChannels\SalesChannelTypes;
use Larasell\Larasell\Storefront\Events\EntryPointCreated;
use Larasell\Larasell\Storefront\Events\EntryPointDeleted;
use Larasell\Larasell\Storefront\Http\Middleware\RestoreStorefrontPath;
use Larasell\Larasell\Storefront\Http\Middleware\SetStorefrontContext;
use Larasell\Larasell\Storefront\Listeners\AuditEntryPointChanges;
use Larasell\Larasell\Storefront\Listeners\KeepServedLocalesAndCurrencies;
use LogicException;

/** Storefront channels: reached through entry points (host plus prefix), with one route copy per language. */
final class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Storefronts::class);
        // Only a storefront request or a job it queued has a current storefront context; elsewhere, pass one.
        $this->app->bind(StorefrontContext::class, fn (): StorefrontContext => StorefrontContext::current()
            ?? throw new LogicException('There is no current StorefrontContext outside a storefront request or a job it queued; pass one, e.g. StorefrontContext::for(\'default\', \'en\').'));
        $this->callAfterResolving(SalesChannelTypes::class, fn (SalesChannelTypes $types) => $types->register(StorefrontContext::TYPE));
    }

    public function boot(): void
    {
        StorefrontRoutes::register();

        // Finds the entry point before routing, so one route copy per language serves every entry point.
        $this->callAfterResolving(Kernel::class, function (Kernel $kernel): void {
            if ($kernel instanceof HttpKernel) {
                $kernel->prependMiddleware(SetStorefrontContext::class);
                $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, RestoreStorefrontPath::class);
            }
        });
        $this->callAfterResolving('router', function (Router $router): void {
            // First in the group, so the routes after it see the path the client sent.
            $router->pushMiddlewareToGroup(StorefrontRoutes::MIDDLEWARE_GROUP, RestoreStorefrontPath::class);
        });

        Event::listen(SalesChannelUpdated::class, KeepServedLocalesAndCurrencies::class);
        Event::listen(EntryPointCreated::class, [AuditEntryPointChanges::class, 'created']);
        Event::listen(EntryPointDeleted::class, [AuditEntryPointChanges::class, 'deleted']);
    }
}
