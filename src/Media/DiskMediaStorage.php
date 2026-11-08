<?php

namespace Larasell\Larasell\Media;

use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Exceptions\MediaNotStored;

/**
 * Keeps media files below larasell.media.path on the disks in larasell.media.disks: public files
 * on the public one, private ones on the private one, which the browser must not reach.
 */
final readonly class DiskMediaStorage implements MediaStorage
{
    public function __construct(private FilesystemManager $filesystem, private Repository $config) {}

    public function store(UploadedFile $file, AssetVisibility $visibility): StoredFile
    {
        $disk = $this->disk($visibility);
        // A random name, so files never collide and their URLs don't give away what they show.
        $path = $file->store((string) $this->config->get('larasell.media.path'), [
            'disk' => $disk,
            'visibility' => $visibility->value,
        ]);

        if ($path === false) {
            throw MediaNotStored::onDisk($disk, $file->getClientOriginalName());
        }

        return new StoredFile($disk, $path);
    }

    public function copy(StoredFile $file, AssetVisibility $visibility): StoredFile
    {
        $disk = $this->disk($visibility);
        $stream = $this->filesystem->disk($file->disk)->readStream($file->path);

        if ($stream === null || ! $this->filesystem->disk($disk)->writeStream($file->path, $stream, ['visibility' => $visibility->value])) {
            throw MediaNotStored::onDisk($disk, basename($file->path));
        }

        return new StoredFile($disk, $file->path);
    }

    public function delete(StoredFile $file): void
    {
        $this->filesystem->disk($file->disk)->delete($file->path);
    }

    public function url(StoredFile $file): string
    {
        return $this->filesystem->disk($file->disk)->url($file->path);
    }

    public function temporaryUrl(StoredFile $file, DateTimeInterface $expiresAt): string
    {
        return $this->filesystem->disk($file->disk)->temporaryUrl($file->path, $expiresAt);
    }

    public function readStream(StoredFile $file)
    {
        return $this->filesystem->disk($file->disk)->readStream($file->path);
    }

    private function disk(AssetVisibility $visibility): string
    {
        return (string) $this->config->get("larasell.media.disks.{$visibility->value}");
    }
}
