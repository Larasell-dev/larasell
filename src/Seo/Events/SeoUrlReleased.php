<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoUrlStatus;

/**
 * An old or gone URL was deleted: an admin removed it, or another page claimed its path.
 * Dispatched once committed.
 */
final readonly class SeoUrlReleased implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public SalesChannelId $salesChannelId,
        public string $locale,
        public string $path,
        /** The resource type key of the page it belonged to, e.g. "product" */
        public string $resourceType,
        public int $resourceId,
        public SeoUrlStatus $status,
    ) {}

    public static function of(SeoUrl $url): self
    {
        return new self(new SalesChannelId($url->sales_channel_id), $url->locale, $url->path, $url->resource_type, $url->resource_id, $url->status);
    }
}
