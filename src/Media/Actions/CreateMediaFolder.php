<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\Events\MediaFolderCreated;
use Larasell\Larasell\Media\Exceptions\MediaFolderNameTaken;
use Larasell\Larasell\Media\Exceptions\MediaFolderNotFound;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaFolders;
use Larasell\Larasell\Media\Models\MediaFolder;

/** Adds a folder to the media library, at the top level or in another folder. */
final readonly class CreateMediaFolder
{
    public function __construct(private MediaFolders $folders) {}

    /**
     * @throws MediaFolderNotFound when the parent folder doesn't exist (any more)
     * @throws MediaFolderNameTaken when a folder in the same parent has the name
     */
    public function handle(CreateMediaFolderPayload $payload): MediaFolder
    {
        return $this->folders->transaction($payload->name, function () use ($payload): MediaFolder {
            if ($payload->parentId !== null) {
                $this->folders->lockTarget($payload->parentId);
            }

            $this->folders->assertNameFree($payload->name, $payload->parentId);

            $folder = MediaFolder::query()->create([
                'parent_id' => $payload->parentId?->value,
                'name' => $payload->name->value,
                'name_key' => $payload->name->key,
            ]);

            MediaFolderCreated::dispatch(MediaFolderId::of($folder), $payload->parentId, $folder->name);

            return $folder;
        });
    }
}
