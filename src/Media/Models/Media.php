<?php

namespace Larasell\Larasell\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Larasell\Larasell\Casts\NullableTranslatableCast;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Translatable;

/**
 * An image or video in the media library. Its original asset is the file as uploaded; renditions
 * made from it will be assets of their own. Write through the actions in
 * Larasell\Larasell\Media\Actions; they keep the files in step with the rows.
 *
 * @property int $id
 * @property int|null $folder_id null at the top level of the library
 * @property MediaType $type
 * @property Translatable|null $title a short name, e.g. in the library or as a caption
 * @property Translatable|null $description longer text, e.g. a caption or notes on its use
 * @property Translatable|null $alt describes the media to people who can't see it
 * @property-read MediaAsset $original
 * @property-read MediaAsset|null $thumbnail made by a queued job, so missing until it ran
 */
class Media extends Model
{
    protected $table = 'larasell_media';

    protected $guarded = [];

    protected $casts = [
        'type' => MediaType::class,
        'title' => NullableTranslatableCast::class,
        'description' => NullableTranslatableCast::class,
        'alt' => NullableTranslatableCast::class,
    ];

    /** @return BelongsTo<MediaFolder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /** @return HasMany<MediaAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class)->orderBy('id');
    }

    /** @return HasOne<MediaAsset, $this> */
    public function original(): HasOne
    {
        return $this->hasOne(MediaAsset::class)->where('role', MediaAsset::ORIGINAL);
    }

    /** @return HasOne<MediaAsset, $this> */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(MediaAsset::class)->where('role', MediaAsset::THUMBNAIL);
    }
}
