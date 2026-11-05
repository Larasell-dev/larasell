<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Contracts\GoneStrategy;
use Larasell\Larasell\Seo\Contracts\HiddenPagePolicy;
use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Storefront\StorefrontContext;
use LogicException;

/**
 * Reads SEO URLs: what a path leads to, and where a page lives. It never writes, and it knows
 * nothing about HTTP, so routing middleware and a REST API can share it.
 */
final class SeoPages
{
    /** @var array<string, string|null> Canonical paths loaded by preload(), by resource and context. */
    private array $preloaded = [];

    public function __construct(
        private readonly SeoResources $resources,
        private readonly SeoUrlManager $manager,
        private readonly PathNormalizer $paths,
        private readonly HiddenPagePolicy $hidden,
    ) {}

    /** What the path below the context's entry point leads to. */
    public function resolve(string $path, StorefrontContext $context): Resolution
    {
        $url = SeoUrl::query()
            ->where('sales_channel_id', $context->sales->channelId)
            ->where('locale', $context->sales->locale->value)
            ->where('path_hash', SeoUrl::hash($this->paths->normalize($path)))
            ->first();

        if ($url === null) {
            return Resolution::notFound();
        }

        if ($url->status === SeoUrlStatus::Gone) {
            foreach ($this->goneStrategies($url) as $strategy) {
                if ($redirect = $strategy->redirect($url, $context)) {
                    return Resolution::redirect($redirect);
                }
            }

            return Resolution::gone($url);
        }

        $type = $this->resources->findByKey($url->resource_type);
        $resource = $this->resources->page($url->resource_type, $url->resource_id);

        if ($resource === null || $type === null) {
            return Resolution::notFound();
        }

        if (! $type->isAvailable($resource, $context->sales)) {
            $redirect = $this->hidden->redirect($resource, $context);

            return $redirect ? Resolution::redirect($redirect) : Resolution::hidden($resource, $url);
        }

        if ($url->status === SeoUrlStatus::Historic) {
            $canonical = $this->manager->canonical($resource, $context->sales);

            return $canonical
                ? Resolution::redirect(new SeoRedirect($context->url($canonical->path), 301))
                : Resolution::notFound();
        }

        return Resolution::found($resource, $url);
    }

    /**
     * The absolute canonical URL of the page in the context (the current request's by default), or
     * null when it has no URL there. Hidden pages keep their URL; use availableUrl() to link only to
     * pages a visitor can open.
     */
    public function url(Model $resource, ?StorefrontContext $context = null): ?string
    {
        $context ??= $this->currentContext();
        $type = $this->resources->for($resource)->key();
        $key = self::key($type, $resource->getKey(), $context->sales);

        $path = array_key_exists($key, $this->preloaded)
            ? $this->preloaded[$key]
            : $this->manager->canonicalPath($type, $resource->getKey(), $context->sales);

        return $path === null ? null : $context->url($path);
    }

    /** The URL of the page, or null when it has none or is not available in the context. */
    public function availableUrl(Model $resource, ?StorefrontContext $context = null): ?string
    {
        $context ??= $this->currentContext();
        $type = $this->resources->find($resource);

        return $type !== null && $type->isAvailable($resource, $context->sales) ? $this->url($resource, $context) : null;
    }

    /**
     * Loads the URLs of many pages with one query per kind of page, so a listing does not query once
     * per link. Later url() calls for them use the loaded paths until the paths change.
     *
     * @param  iterable<Model>  $resources
     */
    public function preload(iterable $resources, ?StorefrontContext $context = null): void
    {
        $context = ($context ?? $this->currentContext())->sales;
        $ids = [];

        foreach ($resources as $resource) {
            $ids[$this->resources->for($resource)->key()][] = $resource->getKey();
        }

        foreach ($ids as $type => $keys) {
            foreach (array_chunk(array_unique($keys), 500) as $chunk) {
                foreach ($chunk as $id) {
                    $this->preloaded[self::key($type, $id, $context)] = null;
                }

                SeoUrl::query()
                    ->where('resource_type', $type)
                    ->whereIn('resource_id', $chunk)
                    ->where('sales_channel_id', $context->channelId)
                    ->where('locale', $context->locale->value)
                    ->where('status', SeoUrlStatus::Canonical->value)
                    ->get(['resource_id', 'path'])
                    ->each(function (SeoUrl $url) use ($type, $context): void {
                        $this->preloaded[self::key($type, $url->resource_id, $context)] = $url->path;
                    });
            }
        }
    }

    /**
     * Drops a preloaded path, e.g. after it changed.
     *
     * @param  string  $type  The resource type's key(), e.g. "product"
     */
    public function forget(string $type, int|string $id, SalesContext $context): void
    {
        unset($this->preloaded[self::key($type, $id, $context)]);
    }

    /** The current storefront request's or job's context; anywhere else the caller must pass one. */
    private function currentContext(): StorefrontContext
    {
        return StorefrontContext::current()
            ?? throw new LogicException('Seo needs a StorefrontContext outside a storefront request: pass one, e.g. StorefrontContext::for(\'default\', \'en\').');
    }

    private static function key(string $type, mixed $id, SalesContext $context): string
    {
        return $type.'|'.$id.'|'.$context->channelId.'|'.$context->locale->value;
    }

    /** @return list<GoneStrategy> */
    private function goneStrategies(SeoUrl $url): array
    {
        $type = $this->resources->findByKey($url->resource_type);
        $strategies = [];

        foreach ((array) config('larasell.seo.gone', []) as $typeClass => $classes) {
            if (is_string($typeClass) && $type instanceof $typeClass) {
                foreach ((array) $classes as $class) {
                    $strategies[] = app($class);
                }
            }
        }

        return $strategies;
    }
}
