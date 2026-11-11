<?php

namespace Larasell\Larasell\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlManager;

/**
 * Gives every page its URL in a channel's locale, after the channel was created or gained the locale.
 * Each page is refreshed in its own transaction, so it can run while admins keep saving; a page saved
 * meanwhile already got its URL from the catalog action, and refreshing it again changes nothing.
 */
final class BackfillSeoUrls implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public SalesChannelId $channelId, public Locale $locale, public Origin $origin)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->channelId->value.'|'.$this->locale->value;
    }

    public function handle(SeoUrlManager $urls, SeoResources $resources, SalesChannels $channels): void
    {
        $context = $channels->for($this->channelId, $this->locale);

        foreach ($resources->all() as $type) {
            foreach ($type->model()::query()->lazyById() as $resource) {
                $urls->refresh($resource, $this->origin, [$context]);
            }
        }
    }
}
