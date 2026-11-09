<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Events\MediaUpdated;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\StoredFile;
use Throwable;

/**
 * Changes a media item's title, description, alt text and visibility. A new visibility moves its assets' files to
 * where files of that visibility live: copied first, the old ones removed once it is committed.
 */
final readonly class UpdateMedia
{
    public function __construct(private ConnectionInterface $db, private MediaStorage $storage) {}

    public function handle(MediaId $mediaId, UpdateMediaPayload $payload): Media
    {
        /** @var list<StoredFile> $copies */
        $copies = [];

        try {
            [$media, $moved] = $this->db->transaction(function () use ($mediaId, $payload, &$copies): array {
                $media = Media::query()->lockForUpdate()->findOrFail($mediaId->value);
                $media->title = $payload->title;
                $media->description = $payload->description;
                $media->alt = $payload->alt;
                $changed = array_keys($media->getDirty());
                $media->save();
                $moved = [];
                $visibilityChanged = false;

                foreach ($media->assets()->lockForUpdate()->get() as $asset) {
                    if ($payload->visibility === null || $asset->visibility === $payload->visibility) {
                        continue;
                    }

                    $old = $asset->file();
                    $copy = $this->storage->copy($old, $payload->visibility);
                    $asset->update(['visibility' => $payload->visibility, 'disk' => $copy->disk, 'path' => $copy->path, 'metadata' => $copy->metadata ?: null]);
                    $visibilityChanged = true;

                    // Some providers, e.g. Mux, change the file itself instead of copying it: nothing to remove.
                    if ([$copy->provider, $copy->disk, $copy->path] !== [$old->provider, $old->disk, $old->path]) {
                        $copies[] = $copy;
                        $moved[] = $old;
                    }
                }

                if ($visibilityChanged) {
                    $changed[] = 'visibility';
                }

                if ($changed !== []) {
                    MediaUpdated::dispatch($mediaId, $changed, $payload->attachments);
                }

                return [$media, $moved];
            });
        } catch (Throwable $exception) {
            array_map($this->storage->delete(...), $copies);

            throw $exception;
        }

        array_map($this->storage->delete(...), $moved);
        $media->unsetRelation('original');

        return $media;
    }
}
