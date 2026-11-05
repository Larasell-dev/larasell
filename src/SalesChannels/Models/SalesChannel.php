<?php

namespace Larasell\Larasell\SalesChannels\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesChannelType;

/**
 * Write through the actions in Larasell\Larasell\SalesChannels\Actions; they keep the cached entry points current.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property SalesChannelType $type
 * @property Locale $default_locale
 * @property Currency $default_currency
 * @property bool $active
 */
class SalesChannel extends Model
{
    protected $table = 'larasell_sales_channels';

    protected $guarded = [];

    protected $casts = [
        'type' => SalesChannelType::class,
        'default_locale' => Locale::class,
        'default_currency' => Currency::class,
        'active' => 'boolean',
    ];

    /** @return HasMany<SalesChannelEntryPoint, $this> */
    public function entryPoints(): HasMany
    {
        return $this->hasMany(SalesChannelEntryPoint::class, 'sales_channel_id');
    }

    /** @return list<Locale> */
    public function locales(): array
    {
        return array_values($this->getConnection()->table('larasell_sales_channel_locales')
            ->where('sales_channel_id', $this->id)->orderBy('id')->pluck('locale')
            ->map(fn (mixed $locale): Locale => Locale::from((string) $locale))->all());
    }

    /** @return list<Currency> */
    public function currencies(): array
    {
        return array_values($this->getConnection()->table('larasell_sales_channel_currencies')
            ->where('sales_channel_id', $this->id)->orderBy('id')->pluck('currency')
            ->map(fn (mixed $currency): Currency => Currency::from((string) $currency))->all());
    }
}
