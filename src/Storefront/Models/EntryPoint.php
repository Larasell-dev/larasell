<?php

namespace Larasell\Larasell\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Storefront\EntryPoint as EntryPointValue;
use Larasell\Larasell\Storefront\EntryPointId;

/**
 * Write through AddEntryPoint and DeleteEntryPoint; they keep the cached entry points current.
 *
 * @property int $id
 * @property int $sales_channel_id
 * @property string $scheme
 * @property string $host
 * @property int|null $port
 * @property string $prefix
 * @property Locale $locale
 * @property Currency|null $default_currency the currency a visit starts in; null for the channel's default
 * @property bool|null $is_primary true on the primary entry point of its channel and locale, null on others
 */
class EntryPoint extends Model
{
    protected $table = 'larasell_storefront_entry_points';

    protected $guarded = [];

    protected $casts = [
        'port' => 'integer',
        'locale' => Locale::class,
        'default_currency' => Currency::class,
        'is_primary' => 'boolean',
    ];

    /** @return BelongsTo<SalesChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    public function toEntryPoint(): EntryPointValue
    {
        return new EntryPointValue(EntryPointId::of($this), new SalesChannelId($this->sales_channel_id), $this->scheme, $this->host, $this->port, $this->prefix, $this->locale, $this->default_currency, $this->is_primary === true);
    }
}
