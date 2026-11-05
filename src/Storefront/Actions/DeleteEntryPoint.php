<?php

namespace Larasell\Larasell\Storefront\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Storefront\EntryPointId;
use Larasell\Larasell\Storefront\Events\EntryPointDeleted;
use Larasell\Larasell\Storefront\Models\EntryPoint;
use Larasell\Larasell\Storefront\Storefronts;

/** Deletes an entry point. When it was primary, the oldest other one of its locale and currency takes over. */
final readonly class DeleteEntryPoint
{
    public function __construct(
        private ConnectionInterface $db,
        private Storefronts $storefronts,
    ) {}

    public function handle(EntryPointId $entryPointId): void
    {
        $this->db->transaction(function () use ($entryPointId): void {
            $channelId = EntryPoint::query()->findOrFail($entryPointId->value)->sales_channel_id;
            SalesChannel::query()->lockForUpdate()->findOrFail($channelId);
            $entryPoint = EntryPoint::query()->lockForUpdate()->findOrFail($entryPointId->value);

            $entryPoint->delete();

            if ($entryPoint->is_primary === true) {
                EntryPoint::query()->where('sales_channel_id', $channelId)
                    ->where('locale', $entryPoint->locale->value)->where('currency', $entryPoint->currency->value)
                    ->orderBy('id')->first()?->update(['is_primary' => true]);
            }

            EntryPointDeleted::dispatch($entryPointId, new SalesChannelId($channelId));
        });

        $this->storefronts->forget();
    }
}
