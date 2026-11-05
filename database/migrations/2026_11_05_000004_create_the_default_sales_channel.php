<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Settings\CurrencySettings;
use Larasell\Larasell\Settings\LocaleSettings;

/**
 * One storefront channel "default" on app.url, in every supported locale and enabled currency. With one
 * locale it has no prefix; with several, every locale gets one ("/de", "/en-gb").
 */
return new class extends Migration
{
    public function up(): void
    {
        $locales = app(LocaleSettings::class)->supported();
        $currencies = app(CurrencySettings::class)->enabled();
        $default = app(LocaleSettings::class)->default();
        $url = parse_url(rtrim((string) config('app.url'), '/')) ?: [];
        $now = now();

        $channelId = DB::table('larasell_sales_channels')->insertGetId([
            'key' => 'default',
            'name' => (string) config('app.name', 'Shop'),
            'type' => 'storefront',
            'default_locale' => $default->value,
            'default_currency' => $currencies[0]->value,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('larasell_sales_channel_locales')->insert(array_map(
            fn (Locale $locale): array => ['sales_channel_id' => $channelId, 'locale' => $locale->value], $locales,
        ));
        DB::table('larasell_sales_channel_currencies')->insert(array_map(
            fn (Currency $currency): array => ['sales_channel_id' => $channelId, 'currency' => $currency->value], $currencies,
        ));

        foreach ($locales as $locale) {
            $prefix = trim(($url['path'] ?? '').(count($locales) > 1 ? '/'.strtolower($locale->value) : ''), '/');

            DB::table('larasell_storefront_entry_points')->insert([
                'sales_channel_id' => $channelId,
                'scheme' => strtolower($url['scheme'] ?? 'http'),
                'host' => strtolower($url['host'] ?? 'localhost'),
                'port' => $url['port'] ?? null,
                'prefix' => $prefix,
                'locale' => $locale->value,
                'currency' => $currencies[0]->value,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        cache()->forget('larasell.sales_channels');
        cache()->forget('larasell.storefront_entry_points');
    }

    public function down(): void
    {
        DB::table('larasell_sales_channels')->where('key', 'default')->delete();
    }
};
