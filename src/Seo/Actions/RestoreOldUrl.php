<?php

namespace Larasell\Larasell\Seo\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Events\SeoUrlRestored;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlId;
use Larasell\Larasell\Seo\SeoUrlManager;
use Larasell\Larasell\Seo\SeoUrlStatus;

/**
 * Makes an old URL its page's current one in its channel and locale.
 *
 * @public
 */
final readonly class RestoreOldUrl
{
    public function __construct(
        private ConnectionInterface $db,
        private SeoUrlManager $urls,
        private SeoResources $resources,
    ) {}

    /**
     * It becomes the custom URL, so renames keep it, unless it is what the name gives anyway; then
     * the URL follows the name again.
     *
     * @throws ModelNotFoundException when it isn't an old URL of a page that still exists
     */
    public function handle(SeoUrlId $url, Origin $origin): void
    {
        $this->db->transaction(function () use ($url, $origin): void {
            $old = SeoUrl::query()
                ->whereKey($url->value)
                ->where('status', SeoUrlStatus::Historic->value)
                ->lockForUpdate()
                ->firstOrFail();
            $page = $this->resources->page($old->resource_type, $old->resource_id)
                ?? throw (new ModelNotFoundException)->setModel(SeoUrl::class, [$old->id]);
            $context = SalesContext::for(new SalesChannelId($old->sales_channel_id), $old->locale);

            if ($old->path === $this->resources->for($page)->path($page, $context)) {
                $this->urls->resetToAutomatic($page, $context, $origin);
            } else {
                $this->urls->setCustom($page, $context, $old->path, $origin);
            }

            SeoUrlRestored::dispatch(new SalesChannelId($old->sales_channel_id), $old->locale, $old->path, $old->resource_type, $old->resource_id, $origin);
        });
    }
}
