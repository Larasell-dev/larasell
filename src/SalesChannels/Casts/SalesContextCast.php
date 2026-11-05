<?php

namespace Larasell\Larasell\SalesChannels\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\SalesContext;

/**
 * Stores a SalesContext in the columns sales_channel_id, locale, currency and entry_point_id. When the
 * entry point was deleted since, the context uses its channel's primary entry point for the locale.
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

        return app(SalesChannels::class)->restore(
            (int) $attributes['sales_channel_id'],
            Locale::from((string) $attributes['locale']),
            Currency::from((string) $attributes['currency']),
            isset($attributes['entry_point_id']) ? (int) $attributes['entry_point_id'] : null,
        );
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
            'sales_channel_id' => $value->channelId,
            'locale' => $value->locale->value,
            'currency' => $value->currency->value,
            'entry_point_id' => $value->entryPoint->id,
        ];
    }
}
