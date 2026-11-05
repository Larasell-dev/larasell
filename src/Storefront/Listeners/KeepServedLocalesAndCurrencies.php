<?php

namespace Larasell\Larasell\Storefront\Listeners;

use Larasell\Larasell\SalesChannels\Events\SalesChannelUpdated;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\Storefront\Models\EntryPoint;

/** Refuses, inside UpdateSalesChannel's transaction, to drop a locale or currency an entry point still serves. */
final class KeepServedLocalesAndCurrencies
{
    public function handle(SalesChannelUpdated $event): void
    {
        if (array_intersect($event->changed, ['locales', 'currencies']) === []) {
            return;
        }

        $channel = SalesChannel::query()->findOrFail($event->channelId);
        $locales = $channel->locales();
        $currencies = $channel->currencies();
        $orphan = EntryPoint::query()->where('sales_channel_id', $channel->id)->orderBy('id')->get()->first(
            fn (EntryPoint $entryPoint): bool => ! in_array($entryPoint->locale, $locales, true) || ! in_array($entryPoint->currency, $currencies, true),
        );

        if ($orphan !== null) {
            throw new InvalidSalesChannel("The entry point [{$orphan->toEntryPoint()->url()}] still serves [{$orphan->locale->value}, {$orphan->currency->value}]; delete it first.");
        }
    }
}
