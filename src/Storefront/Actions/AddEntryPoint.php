<?php

namespace Larasell\Larasell\Storefront\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\ChannelRules;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\Storefront\Events\EntryPointCreated;
use Larasell\Larasell\Storefront\Models\EntryPoint;
use Larasell\Larasell\Storefront\StorefrontContext;
use Larasell\Larasell\Storefront\Storefronts;

/**
 * Lets a storefront channel be reached through a host plus optional prefix in one locale and currency.
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
     * @param  array{url: string, locale: Locale|string, currency?: Currency|string, primary?: bool}  $input  The first entry point of
     *                                                                                                        its locale and currency is the primary one; pass primary to make a later one primary
     */
    public function handle(int $channelId, array $input): EntryPoint
    {
        $entryPoint = $this->db->transaction(function () use ($channelId, $input): EntryPoint {
            $channel = SalesChannel::query()->lockForUpdate()->findOrFail($channelId);

            if ($channel->type !== StorefrontContext::TYPE) {
                throw new InvalidSalesChannel("The sales channel [{$channel->key}] is not a storefront, so it has no entry points.");
            }

            $locale = $this->rules->locale($input['locale']);
            $currency = $this->rules->currency($input['currency'] ?? $channel->default_currency);
            $url = $this->url($input['url'], $locale, $currency, $channel);
            $others = EntryPoint::query()->where('sales_channel_id', $channel->id)
                ->where('locale', $locale->value)->where('currency', $currency->value);
            $primary = ($input['primary'] ?? false) || ! (clone $others)->exists();

            if ($primary) {
                $others->update(['is_primary' => null]);
            }

            $entryPoint = EntryPoint::query()->create([
                ...$url,
                'sales_channel_id' => $channel->id,
                'locale' => $locale,
                'currency' => $currency,
                'is_primary' => $primary ? true : null,
            ]);

            EntryPointCreated::dispatch($entryPoint->id, $channel->id, $locale, $currency);

            return $entryPoint;
        });

        $this->storefronts->forget();

        return $entryPoint;
    }

    /** @return array{scheme: string, host: string, port: int|null, prefix: string} */
    private function url(string $url, Locale $locale, Currency $currency, SalesChannel $channel): array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidSalesChannel("The entry point URL [{$url}] needs a scheme and a host.");
        }

        if (! in_array($locale, $channel->locales(), true)) {
            throw new InvalidSalesChannel("The entry point [{$url}] serves the locale [{$locale->value}], which its channel does not have.");
        }

        if (! in_array($currency, $channel->currencies(), true)) {
            throw new InvalidSalesChannel("The entry point [{$url}] uses the currency [{$currency->value}], which its channel does not have.");
        }

        $entryPoint = [
            'scheme' => strtolower($parts['scheme']),
            'host' => strtolower($parts['host']),
            'port' => $parts['port'] ?? null,
            'prefix' => trim($parts['path'] ?? '', '/'),
        ];

        if (EntryPoint::query()->where('host', $entryPoint['host'])->where('prefix', $entryPoint['prefix'])->exists()) {
            throw new InvalidSalesChannel("The entry point [{$url}] is already used by a sales channel.");
        }

        return $entryPoint;
    }
}
