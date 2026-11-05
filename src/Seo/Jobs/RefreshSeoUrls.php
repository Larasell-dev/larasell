<?php

namespace Larasell\Larasell\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Larasell\Larasell\Seo\SeoUrlManager;

/** Refreshes resources whose paths are built from a resource that changed, e.g. a moved category's children. */
class RefreshSeoUrls implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param  Collection<int, Model>  $resources */
    public function __construct(public Collection $resources)
    {
        $this->afterCommit();
    }

    public function handle(SeoUrlManager $manager): void
    {
        foreach ($this->resources as $resource) {
            $manager->refresh($resource);
        }
    }
}
