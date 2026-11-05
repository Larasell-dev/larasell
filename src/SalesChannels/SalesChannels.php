<?php

namespace Larasell\Larasell\SalesChannels;

use Illuminate\Contracts\Cache\Repository as Cache;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;

/**
 * Reads sales channels from a cache, so building a context costs no query; the sales channel actions
 * forget the cache when they change a channel.
 *
 * @phpstan-type Channel array{id: int, key: string, type: string, active: bool, locales: list<Locale>, currencies: list<Currency>, default_currency: Currency}
 */
final readonly class SalesChannels
{
    private const CACHE_KEY = 'larasell.sales_channels';

    public function __construct(private Cache $cache) {}

    /** The context of a channel (key or ID) in one of its locales and currencies, by default its default currency. */
    public function for(string|int $channel, Locale|string $locale, ?Currency $currency = null): SalesContext
    {
        $found = $this->channel($channel) ?? throw new InvalidSalesChannel("There is no sales channel [{$channel}].");
        $locale = $locale instanceof Locale ? $locale : Locale::tryFrom(Locale::canonicalize($locale));
        $currency ??= $found['default_currency'];

        if ($locale === null || ! in_array($locale, $found['locales'], true)) {
            throw new InvalidSalesChannel("The sales channel [{$found['key']}] does not sell in [".($locale->value ?? 'that locale').'].');
        }

        if (! in_array($currency, $found['currencies'], true)) {
            throw new InvalidSalesChannel("The sales channel [{$found['key']}] does not sell in [{$currency->value}].");
        }

        return new SalesContext($found['key'], $found['id'], $locale, $currency);
    }

    /**
     * A channel by key or ID, or null when there is none.
     *
     * @return Channel|null
     */
    public function channel(string|int $channel): ?array
    {
        $channels = $this->snapshot();

        return is_int($channel) ? ($channels[$channel] ?? null) : array_find($channels, fn (array $found): bool => $found['key'] === $channel);
    }

    /**
     * Every channel, in the order they were created.
     *
     * @return list<Channel>
     */
    public function all(): array
    {
        return array_values($this->snapshot());
    }

    /** Drops the cached channels; the sales channel actions call it when they change one. */
    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /** @return array<int, Channel> */
    private function snapshot(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, fn (): array => SalesChannel::query()->orderBy('id')->get()
            ->mapWithKeys(fn (SalesChannel $channel): array => [$channel->id => [
                'id' => $channel->id,
                'key' => $channel->key,
                'type' => $channel->type,
                'active' => $channel->active,
                'locales' => $channel->locales(),
                'currencies' => $channel->currencies(),
                'default_currency' => $channel->default_currency,
            ]])->all());
    }
}
