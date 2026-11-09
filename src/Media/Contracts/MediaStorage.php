<?php

namespace Larasell\Larasell\Media\Contracts;

use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Media\AssetInspection;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\StoredFile;

/**
 * A media provider: where files live, e.g. Laravel disks or Mux. Each is configured in
 * larasell.media.providers, and an asset remembers which one keeps it, so providers can be mixed
 * and changing the default leaves earlier files where they are. A provider never decides who may
 * see a file; it only keeps private files where the browser can't reach them.
 */
interface MediaStorage
{
    /** The stored file is ready, or pending or processing when the provider works on it. */
    public function store(UploadedFile $file, AssetVisibility $visibility): StoredFile;

    /** A file the provider already has, by its reference there; pending until inspected. */
    public function locate(string $reference, AssetVisibility $visibility): StoredFile;

    /** Checks a file and reads what it can about it, e.g. for a registered one. */
    public function inspect(StoredFile $file): AssetInspection;

    /**
     * Makes the file available with the given visibility. The result may be the same file,
     * e.g. at Mux, which only changes how it plays; otherwise the original stays until deleted.
     */
    public function copy(StoredFile $file, AssetVisibility $visibility): StoredFile;

    public function delete(StoredFile $file): void;

    /** A lasting URL; only for public files. */
    public function url(StoredFile $file): string;

    /** A URL that stops working at $expiresAt. */
    public function temporaryUrl(StoredFile $file, DateTimeInterface $expiresAt): string;

    /** Whether readStream() gives the file's bytes, which thumbnails are made of. */
    public function readable(): bool;

    /** @return resource|null */
    public function readStream(StoredFile $file);
}
