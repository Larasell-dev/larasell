<?php

namespace Larasell\Larasell\Media\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Larasell\Larasell\Media\AssetInspection;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\MediaAssetId;
use Larasell\Larasell\Media\MediaProviders;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Media\Models\MediaAsset;

/**
 * Checks a registered file with its provider and records what it found: ready with its MIME
 * type, size and dimensions, or failed, e.g. when the provider doesn't have it or it isn't the
 * kind of media it was registered as. Queued on larasell.media.thumbnails.queue.
 */
final class InspectMediaAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public readonly MediaAssetId $assetId)
    {
        $this->onQueue(config('larasell.media.thumbnails.queue'));
    }

    public function handle(MediaProviders $providers): void
    {
        $asset = MediaAsset::query()->with('media')->whereKey($this->assetId->value)->first();

        // Deleted before the job ran, or done already.
        if ($asset === null || ! in_array($asset->status, [AssetStatus::Pending, AssetStatus::Processing], true)) {
            return;
        }

        $inspection = $providers->provider($asset->provider)->inspect($asset->file());
        $type = $inspection->mimeType === null ? null : MediaType::forMimeType($inspection->mimeType);

        if ($inspection->status === AssetStatus::Ready && $inspection->mimeType !== null && $type !== $asset->media?->type) {
            $inspection = AssetInspection::failed("The file is of the type [{$inspection->mimeType}], not an {$asset->media?->type->value} media can hold.");
        }

        $asset->record($inspection);
    }

    public function failed(): void
    {
        MediaAsset::query()->whereKey($this->assetId->value)->first()
            ?->record(AssetInspection::failed('Its provider could not be reached to check it.'));
    }
}
