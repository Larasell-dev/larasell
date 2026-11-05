<?php

namespace Larasell\Larasell\SalesChannels\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\ChannelRules;
use Larasell\Larasell\SalesChannels\Events\SalesChannelCreated;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\SalesChannelType;

final readonly class CreateSalesChannel
{
    public function __construct(
        private ConnectionInterface $db,
        private ChannelRules $rules,
        private AddEntryPoint $entryPoints,
        private SalesChannels $channels,
    ) {}

    /**
     * @param  array{
     *     key: string,
     *     name: string,
     *     type?: SalesChannelType|string,
     *     locales: list<Locale|string>,
     *     currencies: list<Currency|string>,
     *     default_locale?: Locale|string,
     *     default_currency?: Currency|string,
     *     active?: bool,
     *     entry_points?: list<array{url: string, locale: Locale|string, currency?: Currency|string}>
     * }  $input  The defaults are the first locale and currency; an entry point's currency is the channel's default currency.
     */
    public function handle(array $input): SalesChannel
    {
        $locales = array_values(array_unique(array_map($this->rules->locale(...), $input['locales']), SORT_REGULAR));
        $currencies = array_values(array_unique(array_map($this->rules->currency(...), $input['currencies']), SORT_REGULAR));
        $defaultLocale = $this->rules->locale($input['default_locale'] ?? $locales[0] ?? null);
        $defaultCurrency = $this->rules->currency($input['default_currency'] ?? $currencies[0] ?? null);
        $this->rules->defaults($locales, $currencies, $defaultLocale, $defaultCurrency);

        $channel = $this->db->transaction(function () use ($input, $locales, $currencies, $defaultLocale, $defaultCurrency): SalesChannel {
            $channel = SalesChannel::query()->create([
                'key' => $input['key'],
                'name' => $input['name'],
                'type' => $input['type'] ?? SalesChannelType::Storefront,
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

            SalesChannelCreated::dispatch($channel->id, $channel->key);

            foreach ($input['entry_points'] ?? [] as $entryPoint) {
                $this->entryPoints->handle($channel->id, $entryPoint);
            }

            return $channel;
        });

        $this->channels->forget();

        return $channel;
    }
}
