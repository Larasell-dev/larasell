<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaId;

/**
 * A media item's thumbnail was made or remade, e.g. a video's poster. Dispatched by the queued
 * GenerateThumbnail job once the asset is saved, so listeners can read it.
 */
final readonly class MediaThumbnailGenerated
{
    use Dispatchable;

    public function __construct(public MediaId $mediaId) {}
}
