<?php

namespace Larasell\Larasell\SalesChannels\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\SalesChannels\Events\EntryPointDeleted;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannelEntryPoint;
use Larasell\Larasell\SalesChannels\SalesChannels;

/** Deletes an entry point. When it was primary, the oldest other one of its locale and currency takes over. */
final readonly class DeleteEntryPoint
{
    public function __construct(
        private ConnectionInterface $db,
        private SalesChannels $channels,
    ) {}

    public function handle(int $entryPointId): void
    {
        $this->db->transaction(function () use ($entryPointId): void {
            $channelId = SalesChannelEntryPoint::query()->findOrFail($entryPointId)->sales_channel_id;
            SalesChannel::query()->lockForUpdate()->findOrFail($channelId);
            $entryPoint = SalesChannelEntryPoint::query()->lockForUpdate()->findOrFail($entryPointId);

            $entryPoint->delete();

            if ($entryPoint->is_primary === true) {
                SalesChannelEntryPoint::query()->where('sales_channel_id', $channelId)
                    ->where('locale', $entryPoint->locale->value)->where('currency', $entryPoint->currency->value)
                    ->orderBy('id')->first()?->update(['is_primary' => true]);
            }

            EntryPointDeleted::dispatch($entryPoint->id, $channelId);
        });

        $this->channels->forget();
    }
}
