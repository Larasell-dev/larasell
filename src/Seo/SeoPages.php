<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Models\ModelEntry;
use Larasell\Larasell\Seo\Contracts\GoneStrategy;
use Larasell\Larasell\Seo\Contracts\HiddenPagePolicy;
use Larasell\Larasell\Seo\Contracts\PathNormalizer;
use Larasell\Larasell\Seo\Models\SeoUrl;

/**
 * Reads SEO URLs: what a path leads to, and where a page lives. It never writes, and it knows
 * nothing about HTTP, so routing middleware and a REST API can share it.
 */
final class SeoPages
{
    /** @var ModelEntry<SeoUrl> */
    private readonly ModelEntry $urls;

    public function __construct(
        private readonly SeoResources $resources,
        private readonly SeoUrlManager $manager,
        private readonly PathNormalizer $paths,
        private readonly HiddenPagePolicy $hidden,
        Repository $config,
    ) {
        $this->urls = new ModelEntry($config, 'larasell.seo.models.seo_url', SeoUrl::class);
    }

    /** What the path below the context's entry point leads to. */
    public function resolve(string $path, SeoContext $context): Resolution
    {
        $url = $this->urls->query()
            ->where('channel', $context->channel)
            ->where('locale', $context->locale->value)
            ->where('path', $this->paths->normalize($path))
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

        $resource = $url->resource;
        $type = $resource instanceof Model ? $this->resources->find($resource) : null;

        if ($resource === null || $type === null) {
            return Resolution::notFound();
        }

        if (! $type->isAvailable($resource, $context)) {
            $redirect = $this->hidden->redirect($resource, $context);

            return $redirect ? Resolution::redirect($redirect) : Resolution::hidden($resource, $url);
        }

        if ($url->status === SeoUrlStatus::Historic) {
            $canonical = $this->manager->canonical($resource, $context);

            return $canonical
                ? Resolution::redirect(new SeoRedirect($context->url($canonical->path), 301))
                : Resolution::notFound();
        }

        return Resolution::found($resource, $url);
    }

    /** The absolute canonical URL of the page, or null when it has none or is not available in the context. */
    public function availableUrl(Model $resource, SeoContext $context): ?string
    {
        $type = $this->resources->find($resource);

        if ($type === null || ! $type->isAvailable($resource, $context)) {
            return null;
        }

        $canonical = $this->manager->canonical($resource, $context);

        return $canonical === null ? null : $context->url($canonical->path);
    }

    /** @return list<GoneStrategy> */
    private function goneStrategies(SeoUrl $url): array
    {
        $type = $this->resources->findByMorphClass($url->resource_type);
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
