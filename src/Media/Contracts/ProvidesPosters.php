<?php

namespace Larasell\Larasell\Media\Contracts;

use Larasell\Larasell\Media\StoredFile;

/**
 * A provider that keeps a video's bytes to itself but makes frames of it, e.g. Mux. Its poster
 * becomes the video's thumbnail, stored with the provider images go to.
 */
interface ProvidesPosters
{
    /** An image of a telling frame, at most $width wide; null when the provider has none (yet). */
    public function poster(StoredFile $file, int $width): ?string;
}
