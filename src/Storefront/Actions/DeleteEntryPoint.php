<?php

namespace Larasell\Larasell\Storefront\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
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

    public function handle(int $entryPointId): void
    {
        $this->db->transaction(function () use ($entryPointId): void {
            $channelId = EntryPoint::query()->findOrFail($entryPointId)->sales_channel_id;
            SalesChannel::query()->lockForUpdate()->findOrFail($channelId);
            $entryPoint = EntryPoint::query()->lockForUpdate()->findOrFail($entryPointId);

            $entryPoint->delete();

            if ($entryPoint->is_primary === true) {
                EntryPoint::query()->where('sales_channel_id', $channelId)
                    ->where('locale', $entryPoint->locale->value)->where('currency', $entryPoint->currency->value)
                    ->orderBy('id')->first()?->update(['is_primary' => true]);
            }

            EntryPointDeleted::dispatch($entryPoint->id, $channelId);
        });

        $this->storefronts->forget();
    }
}
