<?php

namespace Larasell\Larasell\Seo\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Resolution;
use Larasell\Larasell\Seo\SeoPages;

/**
 * @method static Resolution resolve(string $path, SalesContext $context)
 * @method static string|null url(Model $resource, ?SalesContext $context = null)
 * @method static string|null availableUrl(Model $resource, ?SalesContext $context = null)
 * @method static void preload(iterable<Model> $resources, ?SalesContext $context = null)
 *
 * @see SeoPages
 */
class Seo extends Facade
{
    /** SeoPages is scoped to a request or job, so the facade must not keep an earlier instance. */
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return SeoPages::class;
    }
}
