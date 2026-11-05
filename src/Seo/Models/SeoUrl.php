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
 * @property int $sales_channel_id
 * @property string $locale
 * @property string $path
 * @property string $path_hash
 * @property string $base_path_hash
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

    protected static function booted(): void
    {
        static::saving(function (self $url): void {
            $url->path_hash = self::hash($url->path);
            $url->base_path_hash = self::hash(self::withoutNumber($url->path));
        });
    }

    /** What lookups compare instead of the path itself, which may be of any length. */
    public static function hash(string $path): string
    {
        return hash('sha256', $path);
    }

    /** The path without a trailing "-N", e.g. "p/boots" for "p/boots-12". */
    public static function withoutNumber(string $path): string
    {
        return (string) preg_replace('/-\d+$/', '', $path);
    }
}
