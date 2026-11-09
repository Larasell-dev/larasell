<?php

namespace Larasell\Larasell\Media;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Media\Models\MediaAsset;

/** The ID of one file of a media item, e.g. its original. */
final readonly class MediaAssetId extends Id
{
    protected static function model(): string
    {
        return MediaAsset::class;
    }
}
