<?php

namespace Larasell\Larasell\SalesChannels\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\ChannelRules;
use Larasell\Larasell\SalesChannels\Events\SalesChannelUpdated;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesChannels;

final readonly class UpdateSalesChannel
{
    public function __construct(
        private ConnectionInterface $db,
        private ChannelRules $rules,
        private SalesChannels $channels,
    ) {}

    /**
     * Listeners of SalesChannelUpdated may refuse the change by throwing, e.g. a storefront for a locale its entry points still serve.
     *
     * @param  array{name?: string, locales?: list<Locale|string>, currencies?: list<Currency|string>, default_locale?: Locale|string, default_currency?: Currency|string, active?: bool}  $input
     */
    public function handle(SalesChannelId $channelId, array $input): SalesChannel
    {
        $channel = $this->db->transaction(function () use ($channelId, $input): SalesChannel {
            $channel = SalesChannel::query()->lockForUpdate()->findOrFail($channelId->value);
            $locales = isset($input['locales']) ? array_values(array_unique(array_map($this->rules->locale(...), $input['locales']), SORT_REGULAR)) : $channel->locales();
            $currencies = isset($input['currencies']) ? array_values(array_unique(array_map($this->rules->currency(...), $input['currencies']), SORT_REGULAR)) : $channel->currencies();
            $channel->fill(array_intersect_key($input, array_flip(['name', 'active'])));
            $channel->default_locale = isset($input['default_locale']) ? $this->rules->locale($input['default_locale']) : $channel->default_locale;
            $channel->default_currency = isset($input['default_currency']) ? $this->rules->currency($input['default_currency']) : $channel->default_currency;
            $this->rules->defaults($locales, $currencies, $channel->default_locale, $channel->default_currency);

            $changed = array_keys($channel->getDirty());
            $channel->save();

            if ($locales !== $channel->locales()) {
                $this->replace('larasell_sales_channel_locales', 'locale', $channel->id, array_map(fn (Locale $locale): string => $locale->value, $locales));
                $changed[] = 'locales';
            }

            if ($currencies !== $channel->currencies()) {
                $this->replace('larasell_sales_channel_currencies', 'currency', $channel->id, array_map(fn (Currency $currency): string => $currency->value, $currencies));
                $changed[] = 'currencies';
            }

            if ($changed !== []) {
                SalesChannelUpdated::dispatch($channelId, $changed);
            }

            return $channel;
        });

        $this->channels->forget();

        return $channel;
    }

    /** @param  list<string>  $values */
    private function replace(string $table, string $column, int $channelId, array $values): void
    {
        $this->db->table($table)->where('sales_channel_id', $channelId)->delete();
        $this->db->table($table)->insert(array_map(fn (string $value): array => ['sales_channel_id' => $channelId, $column => $value], $values));
    }
}
