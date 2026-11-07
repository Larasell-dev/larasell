<?php

namespace Larasell\Larasell\SalesChannels\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\Exceptions\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\SalesContext;

/**
 * Stores a SalesContext in the columns sales_channel_id, locale and currency.
 *
 * @implements CastsAttributes<SalesContext|null, SalesContext>
 */
final class SalesContextCast implements CastsAttributes
{
    /** Read fresh each time, so a cached context never overwrites columns changed later, such as currency. */
    public bool $withoutObjectCaching = true;

    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?SalesContext
    {
        if (! isset($attributes['sales_channel_id'], $attributes['locale'], $attributes['currency'])) {
            return null;
        }

        // Built as stored: a record keeps its context when its channel later stops selling in that locale or currency.
        $channel = app(SalesChannels::class)->channel(new SalesChannelId((int) $attributes['sales_channel_id']))
            ?? throw InvalidSalesChannel::unknown((string) $attributes['sales_channel_id']);

        return new SalesContext($channel['key'], new SalesChannelId($channel['id']), Locale::from((string) $attributes['locale']), Currency::from((string) $attributes['currency']));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $value instanceof SalesContext) {
            throw new InvalidArgumentException('The sales context must be a '.SalesContext::class.'.');
        }

        return [
            'sales_channel_id' => $value->channelId->value,
            'locale' => $value->locale->value,
            'currency' => $value->currency->value,
        ];
    }
}
