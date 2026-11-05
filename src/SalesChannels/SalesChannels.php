<?php

namespace Larasell\Larasell\SalesChannels;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannelEntryPoint;
use LogicException;

/**
 * Reads sales channels and their entry points. Entry points are cached, so finding the one a
 * request reaches costs no query; the sales channel actions forget the cache when they change one.
 */
final readonly class SalesChannels
{
    private const CACHE_KEY = 'larasell.sales_channels';

    public function __construct(private Cache $cache) {}

    /** The context of a storefront request: the entry point on its host with the longest matching prefix. */
    public function resolve(Request $request): ?SalesContext
    {
        $path = trim(rawurldecode($request->getPathInfo()), '/');
        $match = null;

        foreach ($this->snapshot()['entry_points'] as $entryPoint) {
            if ($this->snapshot()['channels'][$entryPoint->channelId]['active']
                && $entryPoint->serves($request->getHost(), $path)
                && ($match === null || strlen($entryPoint->prefix) > strlen($match->prefix))) {
                $match = $entryPoint;
            }
        }

        return $match === null ? null : $this->context($match);
    }

    /**
     * The context of a channel (key or ID) in a locale, through its primary entry point for the currency
     * (the channel's default currency when none is given and an entry point uses it).
     */
    public function for(string|int $channel, Locale|string $locale, ?Currency $currency = null): SalesContext
    {
        $snapshot = $this->snapshot();
        $channelId = is_int($channel) ? $channel : array_search($channel, array_column($snapshot['channels'], 'key', 'id'), true);
        $locale = $locale instanceof Locale ? $locale : Locale::from(Locale::canonicalize($locale));

        if ($channelId === false || ! isset($snapshot['channels'][$channelId])) {
            throw new LogicException("There is no sales channel [{$channel}].");
        }

        $primaries = array_filter($snapshot['entry_points'], fn (EntryPoint $entryPoint): bool => $entryPoint->primary
            && $entryPoint->channelId === $channelId && $entryPoint->locale === $locale);
        $currency ??= $snapshot['channels'][$channelId]['default_currency'];
        $entryPoint = array_find($primaries, fn (EntryPoint $entryPoint): bool => $entryPoint->currency === $currency)
            ?? array_values($primaries)[0]
            ?? throw new LogicException("The sales channel [{$channel}] has no entry point in [{$locale->value}].");

        return $this->context($entryPoint);
    }

    /**
     * One context per active channel and locale that has an entry point, in the order they were created.
     *
     * @return list<SalesContext>
     */
    public function contexts(): array
    {
        $contexts = [];

        foreach ($this->snapshot()['entry_points'] as $entryPoint) {
            if ($this->snapshot()['channels'][$entryPoint->channelId]['active']) {
                $contexts[$entryPoint->channelId.'|'.$entryPoint->locale->value] ??= $this->for($entryPoint->channelId, $entryPoint->locale);
            }
        }

        return array_values($contexts);
    }

    /** Builds a record's context from what it stored; a deleted entry point falls back to the primary one. */
    public function restore(int $channelId, Locale $locale, Currency $currency, ?int $entryPointId): SalesContext
    {
        $entryPoint = array_find($this->snapshot()['entry_points'], fn (EntryPoint $entryPoint): bool => $entryPoint->id === $entryPointId);

        if ($entryPoint === null) {
            return $this->for($channelId, $locale, $currency);
        }

        $channel = $this->snapshot()['channels'][$channelId];

        return new SalesContext($channel['key'], $channelId, $locale, $currency, $entryPoint);
    }

    /** Drops the cached entry points; the sales channel actions call it when they change one. */
    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    private function context(EntryPoint $entryPoint): SalesContext
    {
        $channel = $this->snapshot()['channels'][$entryPoint->channelId];

        return new SalesContext($channel['key'], $entryPoint->channelId, $entryPoint->locale, $entryPoint->currency, $entryPoint);
    }

    /** @return array{channels: array<int, array{id: int, key: string, active: bool, default_currency: Currency}>, entry_points: list<EntryPoint>} */
    private function snapshot(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => [
            'channels' => SalesChannel::query()->orderBy('id')->get()->mapWithKeys(fn (SalesChannel $channel): array => [$channel->id => [
                'id' => $channel->id,
                'key' => $channel->key,
                'active' => $channel->active,
                'default_currency' => $channel->default_currency,
            ]])->all(),
            'entry_points' => array_values(SalesChannelEntryPoint::query()->orderBy('id')->get()
                ->map(fn (SalesChannelEntryPoint $entryPoint): EntryPoint => $entryPoint->toEntryPoint())->all()),
        ]);
    }
}
