<?php

namespace Larasell\Larasell\Storefront\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\ChannelRules;
use Larasell\Larasell\SalesChannels\Exceptions\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Storefront\EntryPointId;
use Larasell\Larasell\Storefront\Events\EntryPointCreated;
use Larasell\Larasell\Storefront\Models\EntryPoint;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Storefront\Storefronts;

/**
 * Lets a storefront channel be reached through a host plus optional prefix in one locale, optionally
 * starting visits in a currency other than the channel's default.
 * Routes need no change: SetStorefrontContext finds the new entry point on the next request.
 */
final readonly class AddEntryPoint
{
    public function __construct(
        private ConnectionInterface $db,
        private ChannelRules $rules,
        private Storefronts $storefronts,
    ) {}

    /**
     * @param  array{url: string, locale: Locale|string, default_currency?: Currency|string|null, primary?: bool}  $input  The first
     *                                                                                                                     entry point of its locale is the primary one; pass primary to make a later one primary
     */
    public function handle(SalesChannelId $channelId, array $input): EntryPoint
    {
        $entryPoint = $this->db->transaction(function () use ($channelId, $input): EntryPoint {
            $channel = SalesChannel::query()->lockForUpdate()->findOrFail($channelId->value);

            if ($channel->type !== StorefrontContext::TYPE) {
                throw InvalidSalesChannel::notAStorefront($channel->key);
            }

            $locale = $this->rules->locale($input['locale']);
            $currency = isset($input['default_currency']) ? $this->rules->currency($input['default_currency']) : null;
            $url = $this->url($input['url'], $locale, $currency, $channel);
            $others = EntryPoint::query()->where('sales_channel_id', $channel->id)->where('locale', $locale->value);
            $primary = ($input['primary'] ?? false) || ! (clone $others)->exists();

            if ($primary) {
                $others->update(['is_primary' => null]);
            }

            $entryPoint = EntryPoint::query()->create([
                ...$url,
                'sales_channel_id' => $channel->id,
                'locale' => $locale,
                'default_currency' => $currency,
                'is_primary' => $primary ? true : null,
            ]);

            EntryPointCreated::dispatch(EntryPointId::of($entryPoint), $channelId, $locale, $currency);

            return $entryPoint;
        });

        $this->storefronts->forget();

        return $entryPoint;
    }

    /** @return array{scheme: string, host: string, port: int|null, prefix: string} */
    private function url(string $url, Locale $locale, ?Currency $currency, SalesChannel $channel): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw InvalidSalesChannel::entryPointWithoutHost($url);
        }

        if (! in_array($locale, $channel->locales(), true)) {
            throw InvalidSalesChannel::entryPointLocaleMissing($url, $locale->value);
        }

        if ($currency !== null && ! in_array($currency, $channel->currencies(), true)) {
            throw InvalidSalesChannel::entryPointCurrencyMissing($url, $currency->value);
        }

        $entryPoint = [
            'scheme' => strtolower($parts['scheme']),
            'host' => strtolower($parts['host']),
            'port' => $parts['port'] ?? null,
            'prefix' => trim($parts['path'] ?? '', '/'),
        ];

        if (EntryPoint::query()->where('host', $entryPoint['host'])->where('prefix', $entryPoint['prefix'])->exists()) {
            throw InvalidSalesChannel::entryPointTaken($url);
        }

        return $entryPoint;
    }
}
