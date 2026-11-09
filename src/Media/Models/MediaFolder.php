<?php

namespace Larasell\Larasell\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder in the media library. Folders nest without a limit; each media item is in at most one.
 * Only the folder's place changes when it moves, never the files. Write through the actions in
 * Larasell\Larasell\Media\Actions; they keep the tree free of cycles and names unique among siblings.
 *
 * @property int $id
 * @property int|null $parent_id null at the top level
 * @property string $name
 * @property string $name_key the name as compared, see MediaFolderName
 */
class MediaFolder extends Model
{
    protected $table = 'larasell_media_folders';

    protected $guarded = [];

    /** @return BelongsTo<MediaFolder, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    /** @return HasMany<MediaFolder, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')->orderBy('name_key')->orderBy('id');
    }

    /** @return HasMany<Media, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }
}
