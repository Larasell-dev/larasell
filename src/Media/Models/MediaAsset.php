<?php

namespace Larasell\Larasell\Media\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\Contracts\MediaStorage;
use Larasell\Larasell\Media\Exceptions\PrivateMedia;
use Larasell\Larasell\Media\StoredFile;

/**
 * One file of a media item, kept by MediaStorage.
 *
 * @property int $id
 * @property int $media_id
 * @property string $role "original" for the file as uploaded, "thumbnail" for its small WebP
 * @property AssetVisibility $visibility
 * @property string $disk
 * @property string $path
 * @property string $file_name the name it was uploaded with
 * @property string $mime_type
 * @property int $size in bytes
 * @property int|null $width in pixels; null for videos
 * @property int|null $height in pixels; null for videos
 * @property string $checksum SHA-256 of the file
 */
class MediaAsset extends Model
{
    public const ORIGINAL = 'original';

    public const THUMBNAIL = 'thumbnail';

    protected $table = 'larasell_media_assets';

    protected $guarded = [];

    protected $casts = [
        'visibility' => AssetVisibility::class,
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function file(): StoredFile
    {
        return new StoredFile($this->disk, $this->path);
    }

    /**
     * The file's lasting URL.
     *
     * @throws PrivateMedia for a private file, which only has a URL that expires
     */
    public function url(): string
    {
        if ($this->visibility === AssetVisibility::Private) {
            throw PrivateMedia::hasNoLastingUrl($this->file_name);
        }

        return app(MediaStorage::class)->url($this->file());
    }

    /** A URL that stops working at $expiresAt; hand it out only once the requester may see the file. */
    public function temporaryUrl(DateTimeInterface $expiresAt): string
    {
        return app(MediaStorage::class)->temporaryUrl($this->file(), $expiresAt);
    }
}
