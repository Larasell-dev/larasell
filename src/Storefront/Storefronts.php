<?php

namespace Larasell\Larasell\Storefront;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Storefront\Models\EntryPoint as EntryPointModel;
use LogicException;

/**
 * Reads the entry points of storefront channels. They are cached, so finding the one a request
 * reaches costs no query; AddEntryPoint and DeleteEntryPoint forget the cache.
 */
final readonly class Storefronts
{
    private const CACHE_KEY = 'larasell.storefront_entry_points';

    public function __construct(
        private Cache $cache,
        private SalesChannels $channels,
    ) {}

    /** The context of a request: the entry point of an active channel on its host with the longest matching prefix. */
    public function resolve(Request $request): ?StorefrontContext
    {
        $path = trim(rawurldecode($request->getPathInfo()), '/');
        $match = null;

        foreach ($this->entryPoints() as $entryPoint) {
            if (($this->channels->channel($entryPoint->channelId)['active'] ?? false)
                && $entryPoint->serves($request->getHost(), $path)
                && ($match === null || strlen($entryPoint->prefix) > strlen($match->prefix))) {
                $match = $entryPoint;
            }
        }

        return $match === null ? null : $this->context($match);
    }

    /** The storefront context of a sales context, through the primary entry point of its channel, locale and currency. */
    public function for(SalesContext $sales): StorefrontContext
    {
        $entryPoint = array_find($this->entryPoints(), fn (EntryPoint $entryPoint): bool => $entryPoint->primary
            && $entryPoint->channelId === $sales->channelId && $entryPoint->locale === $sales->locale && $entryPoint->currency === $sales->currency);

        if (($this->channels->channel($sales->channelId)['type'] ?? null) !== StorefrontContext::TYPE) {
            throw new LogicException("The sales channel [{$sales->channel}] is not a storefront, so it has no entry points.");
        }

        if ($entryPoint === null) {
            throw new LogicException("The sales channel [{$sales->channel}] has no entry point in [{$sales->locale->value}, {$sales->currency->value}].");
        }

        return new StorefrontContext($sales, $entryPoint);
    }

    /**
     * One context per active storefront channel and locale that has an entry point, through the
     * primary entry point of the channel's default currency where it has one.
     *
     * @return list<StorefrontContext>
     */
    public function contexts(): array
    {
        $contexts = [];

        foreach ($this->entryPoints() as $entryPoint) {
            $channel = $this->channels->channel($entryPoint->channelId);

            if (! ($channel['active'] ?? false) || ! $entryPoint->primary) {
                continue;
            }

            $key = $entryPoint->channelId.'|'.$entryPoint->locale->value;

            if (! isset($contexts[$key]) || $entryPoint->currency === $channel['default_currency']) {
                $contexts[$key] = $this->context($entryPoint);
            }
        }

        return array_values($contexts);
    }

    /** Drops the cached entry points. */
    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function context(EntryPoint $entryPoint): StorefrontContext
    {
        return new StorefrontContext($this->channels->for($entryPoint->channelId, $entryPoint->locale, $entryPoint->currency), $entryPoint);
    }

    /** @return list<EntryPoint> */
    private function entryPoints(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => array_values(EntryPointModel::query()->orderBy('id')->get()
            ->map(fn (EntryPointModel $entryPoint): EntryPoint => $entryPoint->toEntryPoint())->all()));
    }
}
