<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\MediaAssetId;
use Larasell\Larasell\Media\MediaId;

/**
 * One of a media item's files moved to another status, e.g. Mux finished a video or a
 * registered file turned out missing. Dispatched once the asset is saved.
 */
final readonly class MediaAssetStatusChanged
{
    use Dispatchable;

    public function __construct(public MediaId $mediaId, public MediaAssetId $assetId, public AssetStatus $status) {}
}
