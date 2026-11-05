<?php

namespace Larasell\Larasell\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Seo\SeoUrlStatus;

/**
 * One path in one channel and locale. Only SeoUrlManager writes these rows.
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

    /** @return MorphTo<Model, $this> */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function replacement(): MorphTo
    {
        return $this->morphTo();
    }
}
