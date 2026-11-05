<?php

namespace Larasell\Larasell\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Seo\SeoUrlStatus;

/**
 * One path in one channel and locale. Only SeoUrlManager writes these rows.
 *
 * resource_type and replacement_type hold the resource type's key(), such as "product", not a
 * morph class; SeoResources::page() loads the model they point at.
 *
 * @property int $id
 * @property string $channel
 * @property string $locale
 * @property string $path
 * @property string $resource_type
 * @property int $resource_id
 * @property SeoUrlStatus $status
 * @property bool $is_custom
 * @property Carbon|null $went_public_at
 * @property array<string, mixed>|null $gone_snapshot
 * @property string|null $replacement_type
 * @property int|null $replacement_id
 */
class SeoUrl extends Model
{
    /** The longest path the path column holds, in characters. */
    public const MAX_PATH_LENGTH = 512;

    protected $table = 'larasell_seo_urls';

    protected $guarded = [];

    protected $casts = [
        'resource_id' => 'integer',
        'status' => SeoUrlStatus::class,
        'is_custom' => 'boolean',
        'went_public_at' => 'datetime',
        'gone_snapshot' => 'array',
        'replacement_id' => 'integer',
    ];
}
