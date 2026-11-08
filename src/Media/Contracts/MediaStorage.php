<?php

namespace Larasell\Larasell\Media\Contracts;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\StoredFile;

/**
 * Where media files live. Bound to the class in larasell.media.storage; DiskMediaStorage keeps
 * them on Laravel disks. An asset remembers where it was stored, so changing the storage leaves
 * earlier files where they are. Storage never decides who may see a file; it only keeps private
 * files where the browser can't reach them.
 */
interface MediaStorage
{
    public function store(UploadedFile $file, AssetVisibility $visibility): StoredFile;

    /** Copies the file to where files of the given visibility live; the original stays until deleted. */
    public function copy(StoredFile $file, AssetVisibility $visibility): StoredFile;

    public function delete(StoredFile $file): void;

    /** A lasting URL; only for public files. */
    public function url(StoredFile $file): string;

    /** A URL that stops working at $expiresAt. */
    public function temporaryUrl(StoredFile $file, DateTimeInterface $expiresAt): string;

    /** @return resource|null */
    public function readStream(StoredFile $file);
}
