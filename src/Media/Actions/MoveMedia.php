<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\Events\MediaMoved;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\MediaFolders;
use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaMoveSkipReason;
use Larasell\Larasell\Media\Models\Media;

/**
 * Moves media items into a folder or to the top level. Items that no longer exist are skipped
 * and reported, so the others still move.
 */
final readonly class MoveMedia
{
    public function __construct(private ConnectionInterface $db, private MediaFolders $folders) {}

    /** @throws MediaFolderNotFound when the folder doesn't exist (any more); then nothing moves */
    public function handle(MoveMediaPayload $payload): MoveMediaResult
    {
        return $this->db->transaction(function () use ($payload): MoveMediaResult {
            if ($payload->folderId !== null) {
                $this->folders->lockTarget($payload->folderId);
            }

            $ids = array_values(array_unique(array_map(fn (MediaId $id): int => $id->value, $payload->mediaIds)));
            // In ID order, so two moves of overlapping items lock them in the same order and can't deadlock.
            $media = Media::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get(['id', 'folder_id'])->keyBy('id');
            $folderId = $payload->folderId?->value;
            $changed = $media->filter(fn (Media $item): bool => $item->folder_id !== $folderId)->keys()->all();

            if ($changed !== []) {
                Media::query()->whereKey($changed)->update(['folder_id' => $folderId]);
                MediaMoved::dispatch(MediaId::many($changed), $payload->folderId);
            }

            $skipped = [];

            foreach ($ids as $id) {
                if (! $media->has($id)) {
                    $skipped[$id] = MediaMoveSkipReason::Deleted;
                }
            }

            return new MoveMediaResult(MediaId::many($media->keys()->all()), $skipped);
        });
    }
}
