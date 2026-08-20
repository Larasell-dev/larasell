<?php

namespace Larasell\Larasell\Settings;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Setting;
use InvalidArgumentException;

final class CurrencySettings
{
    private const KEY = 'currencies';

    /** @return list<Currency> */
    public function enabled(): array
    {
        $codes = $this->model()::query()->where('key', self::KEY)->first()?->getAttribute('value')['enabled'] ?? [Currency::USD->value];

        $currencies = collect($codes)
            ->map(fn (mixed $code): ?Currency => is_string($code) ? Currency::tryFrom($code) : null)
            ->filter()
            ->unique(fn (Currency $currency): string => $currency->value)
            ->values()
            ->all();

        return $currencies === [] ? [Currency::USD] : $currencies;
    }

    /** @return list<string> */
    public function enabledCodes(): array
    {
        return array_map(fn (Currency $currency): string => $currency->value, $this->enabled());
    }

    /** @param list<Currency> $currencies */
    public function save(array $currencies): void
    {
        if ($currencies === []) {
            throw new InvalidArgumentException('At least one currency must be enabled.');
        }

        $enabled = Collection::make($currencies)
            ->unique(fn (Currency $currency): string => $currency->value)
            ->map(fn (Currency $currency): string => $currency->value)
            ->values()
            ->all();

        $this->model()::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => ['enabled' => $enabled]],
        );
    }

    /** @return class-string<Model> */
    private function model(): string
    {
        return config('larasell.models.setting', Setting::class);
    }
}
