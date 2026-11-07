<?php

namespace Larasell\Larasell\SalesChannels\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\ChannelRules;
use Larasell\Larasell\SalesChannels\Events\SalesChannelCreated;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesChannels;

final readonly class CreateSalesChannel
{
    public function __construct(
        private ConnectionInterface $db,
        private ChannelRules $rules,
        private SalesChannels $channels,
    ) {}

    /**
     * @param  array{
     *     key: string,
     *     name: string,
     *     type: string,
     *     locales: list<Locale|string>,
     *     currencies: list<Currency|string>,
     *     default_locale?: Locale|string,
     *     default_currency?: Currency|string,
     *     active?: bool,
     * }  $input  The type is a key registered with SalesChannelTypes, e.g. "storefront"; the defaults are the first locale and currency.
     *            How customers reach the channel is up to its type, e.g. Larasell\Larasell\Storefront\Actions\AddEntryPoint.
     */
    public function handle(array $input): SalesChannel
    {
        $key = $this->rules->key($input['key']);
        $type = $this->rules->type($input['type']);
        $locales = array_values(array_unique(array_map($this->rules->locale(...), $input['locales']), SORT_REGULAR));
        $currencies = array_values(array_unique(array_map($this->rules->currency(...), $input['currencies']), SORT_REGULAR));
        $defaultLocale = $this->rules->locale($input['default_locale'] ?? $locales[0] ?? null);
        $defaultCurrency = $this->rules->currency($input['default_currency'] ?? $currencies[0] ?? null);
        $this->rules->defaults($locales, $currencies, $defaultLocale, $defaultCurrency);

        $channel = $this->db->transaction(function () use ($input, $key, $type, $locales, $currencies, $defaultLocale, $defaultCurrency): SalesChannel {
            $channel = SalesChannel::query()->create([
                'key' => $key,
                'name' => $input['name'],
                'type' => $type,
                'default_locale' => $defaultLocale,
                'default_currency' => $defaultCurrency,
                'active' => $input['active'] ?? true,
            ]);

            $this->db->table('larasell_sales_channel_locales')->insert(array_map(
                fn (Locale $locale): array => ['sales_channel_id' => $channel->id, 'locale' => $locale->value], $locales,
            ));
            $this->db->table('larasell_sales_channel_currencies')->insert(array_map(
                fn (Currency $currency): array => ['sales_channel_id' => $channel->id, 'currency' => $currency->value], $currencies,
            ));

            SalesChannelCreated::dispatch(SalesChannelId::of($channel), $channel->key, $type);

            return $channel;
        });

        $this->channels->forget();

        return $channel;
    }
}
