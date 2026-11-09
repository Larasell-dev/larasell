<?php

namespace Larasell\Larasell\Media;

use DateTimeInterface;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Exceptions\MediaNotStored;

/**
 * The local and s3 drivers: files below a path on two Laravel disks, public files on one the
 * browser can reach, private ones on one it can't. Ready as soon as they're written.
 */
final readonly class DiskMediaStorage implements MediaStorage
{
    /** @param  array{public: string, private: string}  $disks */
    public function __construct(
        private FilesystemManager $filesystem,
        private string $name,
        private array $disks,
        private string $path,
    ) {}

    public function store(UploadedFile $file, AssetVisibility $visibility): StoredFile
    {
        $disk = $this->disks[$visibility->value];
        // A random name, so files never collide and their URLs don't give away what they show.
        $path = $file->store($this->path, ['disk' => $disk, 'visibility' => $visibility->value]);

        if ($path === false) {
            throw MediaNotStored::onDisk($disk, $file->getClientOriginalName());
        }

        return new StoredFile($this->name, $disk, $path);
    }

    public function locate(string $reference, AssetVisibility $visibility): StoredFile
    {
        return new StoredFile($this->name, $this->disks[$visibility->value], $reference, AssetStatus::Pending);
    }

    public function inspect(StoredFile $file): AssetInspection
    {
        $disk = $this->filesystem->disk((string) $file->disk);

        if (! $disk->exists($file->path)) {
            return AssetInspection::failed("The file [{$file->path}] is not on the disk [{$file->disk}].");
        }

        $contents = (string) $disk->get($file->path);
        $dimensions = @getimagesizefromstring($contents);

        return new AssetInspection(
            AssetStatus::Ready,
            mimeType: (string) $disk->mimeType($file->path),
            size: strlen($contents),
            width: $dimensions === false ? null : $dimensions[0],
            height: $dimensions === false ? null : $dimensions[1],
            checksum: hash('sha256', $contents),
        );
    }

    public function copy(StoredFile $file, AssetVisibility $visibility): StoredFile
    {
        $disk = $this->disks[$visibility->value];
        $stream = $this->filesystem->disk((string) $file->disk)->readStream($file->path);

        if ($stream === null || ! $this->filesystem->disk($disk)->writeStream($file->path, $stream, ['visibility' => $visibility->value])) {
            throw MediaNotStored::onDisk($disk, basename($file->path));
        }

        return new StoredFile($this->name, $disk, $file->path);
    }

    public function delete(StoredFile $file): void
    {
        $this->filesystem->disk((string) $file->disk)->delete($file->path);
    }

    public function url(StoredFile $file): string
    {
        return $this->filesystem->disk((string) $file->disk)->url($file->path);
    }

    public function temporaryUrl(StoredFile $file, DateTimeInterface $expiresAt): string
    {
        return $this->filesystem->disk((string) $file->disk)->temporaryUrl($file->path, $expiresAt);
    }

    public function readable(): bool
    {
        return true;
    }

    public function readStream(StoredFile $file)
    {
        return $this->filesystem->disk((string) $file->disk)->readStream($file->path);
    }
}
