<?php

namespace Larasell\Larasell\Seo\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Larasell\Larasell\Seo\Resolution;
use Larasell\Larasell\Seo\SeoContext;
use Larasell\Larasell\Seo\SeoPages;

/**
 * @method static Resolution resolve(string $path, SeoContext $context)
 * @method static string|null availableUrl(Model $resource, SeoContext $context)
 *
 * @see SeoPages
 */
class Seo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SeoPages::class;
    }
}
