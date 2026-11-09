<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\AssetInspection;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\Models\MediaAsset;
use Larasell\Larasell\Media\MuxMediaStorage;

/**
 * Records what a Mux webhook says about a video: Mux made the asset, it is ready to play, or it
 * failed. The app checks the request with MuxWebhookSignature first, then passes its decoded
 * body here. Events for videos the library doesn't know, and late or repeated ones, change nothing.
 *
 * @phpstan-import-type MuxAsset from MuxMediaStorage
 */
final readonly class HandleMuxWebhook
{
    public function __construct(private ConnectionInterface $db, private MediaProviders $providers) {}

    /** @param  array{type?: string, data?: MuxAsset}  $event */
    public function handle(array $event): void
    {
        $data = $event['data'] ?? [];
        $inspection = match ($event['type'] ?? '') {
            'video.asset.created' => new AssetInspection(AssetStatus::Processing, path: $data['id'] ?? null),
            'video.asset.ready' => MuxMediaStorage::inspection(['status' => 'ready'] + $data),
            'video.asset.errored' => MuxMediaStorage::inspection(['status' => 'errored'] + $data),
            'video.upload.errored', 'video.upload.cancelled' => AssetInspection::failed('The upload to Mux did not finish.'),
            default => null,
        };
        // An upload event names the upload; an asset event the asset, and the upload it came from.
        $references = array_filter([$data['id'] ?? null, $data['upload_id'] ?? null, $data['asset_id'] ?? null]);

        if ($inspection === null || $references === []) {
            return;
        }

        $this->db->transaction(function () use ($references, $inspection): void {
            MediaAsset::query()
                ->whereIn('provider', $this->providers->namesWithDriver('mux'))
                ->whereIn('path', $references)
                ->lockForUpdate()
                ->get()
                ->each(fn (MediaAsset $asset) => $asset->record($inspection));
        });
    }
}
