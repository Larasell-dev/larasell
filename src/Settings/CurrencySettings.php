<?php

namespace Larasell\Larasell\Settings;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\ModelRegistry;

final class CurrencySettings
{
    private const KEY = 'currencies';

    public function __construct(private readonly ModelRegistry $models) {}

    /** @return list<Currency> */
    public function enabled(): array
    {
        $codes = $this->models->setting->query()->where('key', self::KEY)->first()?->getAttribute('value')['enabled'] ?? [Currency::USD->value];

        $currencies = collect(is_array($codes) ? $codes : [])
            ->map(fn (mixed $code): ?Currency => is_string($code) ? Currency::tryFrom($code) : null)
            ->filter()
            ->unique(fn (Currency $currency): string => $currency->value)
            ->values()
            ->all();

        return $currencies === [] ? [Currency::USD] : array_values($currencies);
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

        $this->models->setting->query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => ['enabled' => $enabled]],
        );
    }
}
