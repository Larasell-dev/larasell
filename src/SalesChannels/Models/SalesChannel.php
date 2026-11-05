<?php

namespace Larasell\Larasell\SalesChannels\Models;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/**
 * Write through the actions in Larasell\Larasell\SalesChannels\Actions; they keep the cached channels current.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $type a key registered with Larasell\Larasell\SalesChannels\SalesChannelTypes, e.g. "storefront"
 * @property Locale $default_locale
 * @property Currency $default_currency
 * @property bool $active
 */
class SalesChannel extends Model
{
    protected $table = 'larasell_sales_channels';

    protected $guarded = [];

    protected $casts = [
        'default_locale' => Locale::class,
        'default_currency' => Currency::class,
        'active' => 'boolean',
    ];

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
