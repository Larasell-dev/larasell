<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\Events\MediaFolderRenamed;
use Larasell\Larasell\Media\Exceptions\MediaFolderNameTaken;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaFolders;
use Larasell\Larasell\Media\Models\MediaFolder;

/** Gives a media folder another name. Changing only its case is a rename too. */
final readonly class RenameMediaFolder
{
    public function __construct(private MediaFolders $folders) {}

    /** @throws MediaFolderNameTaken when another folder in the same parent has the name */
    public function handle(MediaFolderId $folderId, RenameMediaFolderPayload $payload): MediaFolder
    {
        return $this->folders->transaction($payload->name, function () use ($folderId, $payload): MediaFolder {
            $folder = MediaFolder::query()->lockForUpdate()->findOrFail($folderId->value);
            $parentId = $folder->parent_id === null ? null : new MediaFolderId($folder->parent_id);
            $this->folders->assertNameFree($payload->name, $parentId, except: $folder);

            $oldName = $folder->name;
            $folder->name = $payload->name->value;
            $folder->name_key = $payload->name->key;

            if ($folder->isDirty('name')) {
                $folder->save();
                MediaFolderRenamed::dispatch($folderId, $oldName, $folder->name);
            }

            return $folder;
        });
    }
}
