<?php

namespace Larasell\Larasell\Media;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Media\Models\Media;

final readonly class MediaId extends Id
{
    protected static function model(): string
    {
        return Media::class;
    }
}
