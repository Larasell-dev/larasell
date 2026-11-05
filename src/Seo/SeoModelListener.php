<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Seo\Jobs\RefreshSeoUrls;
use WeakMap;

/**
 * Keeps URLs in step with saved and deleted resources, for every registered resource type.
 */
final class SeoModelListener
{
    /** @var WeakMap<Model, Collection<int, Model>> */
    private WeakMap $dependents;

    public function __construct(
        private readonly SeoResources $resources,
        private readonly SeoUrlManager $manager,
    ) {
        $this->dependents = new WeakMap;
    }

    public function saved(Model $model): void
    {
        if ($this->resources->find($model) !== null) {
            $this->manager->refresh($model);
        }
    }

    public function deleting(Model $model): void
    {
        $type = $this->resources->find($model);

        if ($type !== null) {
            // Collected now: once the row is gone, the database may already have detached them.
            $this->dependents[$model] = $type->dependents($model);
            $this->manager->markGone($model);
        }
    }

    public function deleted(Model $model): void
    {
        $dependents = $this->dependents[$model] ?? null;
        unset($this->dependents[$model]);

        if ($dependents !== null && $dependents->isNotEmpty()) {
            RefreshSeoUrls::dispatch($dependents);
        }
    }
}
