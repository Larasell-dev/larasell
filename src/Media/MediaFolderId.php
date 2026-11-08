<?php

namespace Larasell\Larasell\Media;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Media\Models\MediaFolder;

final readonly class MediaFolderId extends Id
{
    protected static function model(): string
    {
        return MediaFolder::class;
    }
}
