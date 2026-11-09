<?php

namespace Larasell\Larasell\Media;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Larasell\Larasell\Media\Exceptions\MediaFolderNameTaken;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\Models\MediaFolder;

/** What the media actions share about folders: finding a target folder, and keeping names unique among siblings. */
final readonly class MediaFolders
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * Runs the write in a transaction. The unique index has the last word on names: when two
     * requests pass the check at once, the second one's insert fails and is reported the same way.
     * A deadlock is retried: two opposite moves (A into B, B into A) lock the same folders in
     * reverse order, and the retry sees the other's move and refuses the loop.
     *
     * @template T
     *
     * @param  Closure(): T  $write
     *
     * @param-immediately-invoked-callable $write
     *
     * @return T
     *
     * @throws MediaFolderNameTaken
     */
    public function transaction(MediaFolderName $name, Closure $write): mixed
    {
        try {
            return $this->db->transaction($write, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw MediaFolderNameTaken::named($name->value, $exception);
        }
    }

    /**
     * Locks the folder something goes into, so it can't be deleted until the transaction ends.
     * Shared, so uploads and moves into the same folder don't wait for each other.
     *
     * @throws MediaFolderNotFound
     */
    public function lockTarget(MediaFolderId $folderId): MediaFolder
    {
        return MediaFolder::query()->sharedLock()->find($folderId->value) ?? throw MediaFolderNotFound::id($folderId);
    }

    /** @throws MediaFolderNameTaken when a sibling other than $except already has the name */
    public function assertNameFree(MediaFolderName $name, ?MediaFolderId $parentId, ?MediaFolder $except = null): void
    {
        $taken = MediaFolder::query()
            ->where('parent_id', $parentId?->value)
            ->where('name_key', $name->key)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->id))
            ->exists();

        if ($taken) {
            throw MediaFolderNameTaken::named($name->value);
        }
    }
}
