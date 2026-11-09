<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Media\Events\MediaFolderDeleted;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotEmpty;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\Models\Media;
use Larasell\Larasell\Media\Models\MediaFolder;

/** Deletes an empty media folder. One with media or folders in it stays, so a delete never removes files. */
final readonly class DeleteMediaFolder
{
    public function __construct(private ConnectionInterface $db) {}

    /** @throws MediaFolderNotEmpty when media or folders are still in it */
    public function handle(MediaFolderId $folderId): void
    {
        $this->db->transaction(function () use ($folderId): void {
            // Locked before counting: an upload or move into it waits, then finds it gone.
            $folder = MediaFolder::query()->lockForUpdate()->findOrFail($folderId->value);
            $mediaCount = Media::query()->where('folder_id', $folder->id)->count();
            $folderCount = MediaFolder::query()->where('parent_id', $folder->id)->count();

            if ($mediaCount > 0 || $folderCount > 0) {
                throw MediaFolderNotEmpty::has($folderId, $mediaCount, $folderCount);
            }

            MediaFolderDeleted::dispatch($folderId, $folder->parent_id === null ? null : new MediaFolderId($folder->parent_id), $folder->name);

            $folder->delete();
        });
    }
}
