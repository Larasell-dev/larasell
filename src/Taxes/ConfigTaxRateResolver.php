<?php

namespace Larasell\Larasell\Taxes;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use Larasell\Larasell\Contracts\TaxRateResolver;
use Larasell\Larasell\Enums\TaxTreatment;

final readonly class ConfigTaxRateResolver implements TaxRateResolver
{
    public function __construct(private Repository $config) {}

    public function resolve(string $category, TaxJurisdiction $jurisdiction): ?TaxRateRule
    {
        $rules = $this->config->get('larasell.taxes.rates', []);

        if (! is_array($rules)) {
            throw new InvalidArgumentException('The configured tax rates must be an array.');
        }

        foreach ($this->jurisdictionKeys($jurisdiction) as $key) {
            $rule = $rules[$key][$category] ?? null;

            if ($rule === null) {
                continue;
            }

            if (! is_array($rule)
                || ! isset($rule['identifier'], $rule['name'], $rule['rate'])
                || ! is_string($rule['identifier'])
                || ! is_string($rule['name'])
                || ! is_string($rule['rate'])
                || (isset($rule['treatment']) && ! is_string($rule['treatment']))) {
                throw new InvalidArgumentException("The configured tax rule [{$key}.{$category}] is invalid.");
            }

            return new TaxRateRule(
                identifier: $rule['identifier'],
                name: $rule['name'],
                rate: TaxRate::from($rule['rate']),
                treatment: TaxTreatment::from($rule['treatment'] ?? TaxTreatment::Taxable->value),
            );
        }

        return null;
    }

    /** @return array<int, string> */
    private function jurisdictionKeys(TaxJurisdiction $jurisdiction): array
    {
        $country = strtoupper($jurisdiction->country);

        return $jurisdiction->state === null
            ? [$country]
            : [$country.'-'.strtoupper($jurisdiction->state), $country];
    }
}
