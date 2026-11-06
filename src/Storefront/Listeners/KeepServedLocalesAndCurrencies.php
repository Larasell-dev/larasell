<?php

namespace Larasell\Larasell\Storefront\Listeners;

use Larasell\Larasell\SalesChannels\Events\SalesChannelUpdated;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\Storefront\Models\EntryPoint;

/** Refuses, inside UpdateSalesChannel's transaction, to drop a locale an entry point serves or a currency one starts visits in. */
final class KeepServedLocalesAndCurrencies
{
    public function handle(SalesChannelUpdated $event): void
    {
        if (array_intersect($event->changed, ['locales', 'currencies']) === []) {
            return;
        }

        $channel = SalesChannel::query()->findOrFail($event->channelId->value);
        $locales = $channel->locales();
        $currencies = $channel->currencies();
        $orphan = EntryPoint::query()->where('sales_channel_id', $channel->id)->orderBy('id')->get()->first(
            fn (EntryPoint $entryPoint): bool => ! in_array($entryPoint->locale, $locales, true)
                || ($entryPoint->default_currency !== null && ! in_array($entryPoint->default_currency, $currencies, true)),
        );

        if ($orphan === null) {
            return;
        }

        if (! in_array($orphan->locale, $locales, true)) {
            throw new InvalidSalesChannel("The entry point [{$orphan->toEntryPoint()->url()}] still serves [{$orphan->locale->value}]; delete it first.");
        }

        throw new InvalidSalesChannel("The entry point [{$orphan->toEntryPoint()->url()}] still starts visits in [{$orphan->default_currency?->value}]; delete it first.");
    }
}
