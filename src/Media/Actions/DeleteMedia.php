<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Events\MediaDeleted;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaAsset;

/** Deletes a media item with its assets, then removes their files. */
final readonly class DeleteMedia
{
    public function __construct(private ConnectionInterface $db, private MediaStorage $storage) {}

    public function handle(MediaId $mediaId): void
    {
        $assets = $this->db->transaction(function () use ($mediaId) {
            $media = Media::query()->lockForUpdate()->findOrFail($mediaId->value);
            $assets = $media->assets()->get();

            MediaDeleted::dispatch($mediaId);

            $media->delete();

            return $assets;
        });

        // Only once the rows are gone, so a refused delete keeps every file.
        $assets->each(fn (MediaAsset $asset) => $this->storage->delete($asset->file()));
    }
}
