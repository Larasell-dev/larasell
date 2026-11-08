<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Chronicle\Exceptions\StructuredLogicException;
use Larasell\Larasell\Media\Events\MediaFolderMoved;
use Larasell\Larasell\Media\Exceptions\MediaFolderCycle;
use Larasell\Larasell\Media\Exceptions\MediaFolderNameTaken;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaFolderName;
use Larasell\Larasell\Media\MediaFolders;
use Larasell\Larasell\Media\Models\MediaFolder;

/** Moves a media folder into another folder or to the top level. Its subfolders and media move with it. */
final readonly class MoveMediaFolder
{
    public function __construct(private MediaFolders $folders) {}

    /**
     * @throws MediaFolderNotFound when the new parent doesn't exist (any more)
     * @throws MediaFolderCycle when the new parent is the folder or one of its subfolders
     * @throws MediaFolderNameTaken when a folder in the new parent has the same name
     */
    public function handle(MediaFolderId $folderId, MoveMediaFolderPayload $payload): MediaFolder
    {
        $parentId = $payload->parentId;
        $name = new MediaFolderName(MediaFolder::query()->findOrFail($folderId->value)->name);

        return $this->folders->transaction($name, function () use ($folderId, $parentId): MediaFolder {
            $folder = MediaFolder::query()->lockForUpdate()->findOrFail($folderId->value);

            if ($parentId !== null) {
                $this->assertNoCycle($folder, $parentId);
            }

            $oldParentId = $folder->parent_id === null ? null : new MediaFolderId($folder->parent_id);
            $folder->parent_id = $parentId?->value;

            if (! $folder->isDirty('parent_id')) {
                return $folder;
            }

            $this->folders->assertNameFree(new MediaFolderName($folder->name), $parentId, except: $folder);
            $folder->save();

            MediaFolderMoved::dispatch($folderId, $oldParentId, $parentId);

            return $folder;
        });
    }

    /**
     * Walks up from the new parent, locking each folder on the way: a concurrent move of one of
     * them waits, and its result is read before deciding, so two moves can't close a loop together.
     * Locking the parent also keeps it from being deleted until the move is committed.
     */
    private function assertNoCycle(MediaFolder $folder, MediaFolderId $parentId): void
    {
        $seen = [];
        $id = $parentId->value;

        while ($id !== null) {
            if ($id === $folder->id) {
                throw MediaFolderCycle::intoItself(MediaFolderId::of($folder), $parentId);
            }

            // The tree had no loop before this move, so walking up must end; a repeat means it had.
            if (isset($seen[$id])) {
                throw new StructuredLogicException(
                    errorCode: 'larasell.media.folder-tree-has-cycle',
                    message: "The media folder tree already loops through folder [{$id}].",
                    why: 'Something wrote parent_id directly, past MoveMediaFolder.',
                    fix: 'Set parent_id of one folder in the loop to null, then move it back where it belongs.',
                );
            }

            $seen[$id] = true;
            $id = (MediaFolder::query()->lockForUpdate()->find($id) ?? throw MediaFolderNotFound::id(new MediaFolderId($id)))->parent_id;
        }
    }
}
