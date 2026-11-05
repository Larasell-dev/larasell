<?php

namespace Larasell\Larasell\SalesChannels\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\ChannelRules;
use Larasell\Larasell\SalesChannels\Events\EntryPointCreated;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannelEntryPoint;
use Larasell\Larasell\SalesChannels\SalesChannels;

/**
 * Lets a channel be reached through a host plus optional prefix in one locale and currency. Routes
 * need no change: the storefront middleware finds the new entry point on the next request.
 */
final readonly class AddEntryPoint
{
    public function __construct(
        private ConnectionInterface $db,
        private ChannelRules $rules,
        private SalesChannels $channels,
    ) {}

    /**
     * @param  array{url: string, locale: Locale|string, currency?: Currency|string, primary?: bool}  $input  The first entry point of
     *                                                                                                        its locale and currency is the primary one; pass primary to make a later one primary
     */
    public function handle(int $channelId, array $input): SalesChannelEntryPoint
    {
        $entryPoint = $this->db->transaction(function () use ($channelId, $input): SalesChannelEntryPoint {
            $channel = SalesChannel::query()->lockForUpdate()->findOrFail($channelId);
            $locale = $this->rules->locale($input['locale']);
            $currency = $this->rules->currency($input['currency'] ?? $channel->default_currency);
            $url = $this->rules->entryPoint($input['url'], $locale, $currency, $channel->locales(), $channel->currencies());
            $others = SalesChannelEntryPoint::query()->where('sales_channel_id', $channel->id)
                ->where('locale', $locale->value)->where('currency', $currency->value);
            $primary = ($input['primary'] ?? false) || ! (clone $others)->exists();

            if ($primary) {
                $others->update(['is_primary' => null]);
            }

            $entryPoint = SalesChannelEntryPoint::query()->create([
                ...$url,
                'sales_channel_id' => $channel->id,
                'locale' => $locale,
                'currency' => $currency,
                'is_primary' => $primary ? true : null,
            ]);

            EntryPointCreated::dispatch($entryPoint->id, $channel->id, $locale, $currency);

            return $entryPoint;
        });

        $this->channels->forget();

        return $entryPoint;
    }
}
