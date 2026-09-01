<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Price;

final class TaxSnapshotFactory
{
    /** @return array<string, mixed> */
    public function order(TaxResult $result): array
    {
        $components = [];

        foreach ($result->lines as $line) {
            foreach ($line->components as $component) {
                $key = implode('|', [
                    $component->identifier,
                    $component->rate->percentage(),
                    $component->jurisdiction->identifier,
                ]);

                if (! isset($components[$key])) {
                    $components[$key] = $this->component($component);

                    continue;
                }

                $components[$key]['amount'] = Price::fromArray($components[$key]['amount'])
                    ->add($component->amount)
                    ->toArray();
            }
        }

        ksort($components, SORT_STRING);

        return [
            'version' => 1,
            'status' => $result->status->value,
            'price_mode' => $result->priceMode->value,
            'taxable_amount' => $result->taxableAmount()->toArray(),
            'tax_amount' => $result->taxAmount()->toArray(),
            'jurisdiction' => $result->jurisdiction === null ? null : $this->jurisdiction($result->jurisdiction),
            'components' => array_values($components),
            'metadata' => $result->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function line(TaxLineResult $line, TaxPriceMode $priceMode): array
    {
        return [
            'version' => 1,
            'price_mode' => $priceMode->value,
            'category' => $line->category,
            'treatment' => $line->treatment->value,
            'gross_amount' => $line->amount->add($line->discountAmount)->toArray(),
            'amount' => $line->amount->toArray(),
            'discount_amount' => $line->discountAmount->toArray(),
            'taxable_amount' => $line->taxableAmount->toArray(),
            'tax_amount' => $line->taxAmount->toArray(),
            'components' => array_map($this->component(...), $line->components),
        ];
    }

    /** @return array<string, mixed> */
    private function component(TaxComponent $component): array
    {
        return [
            'identifier' => $component->identifier,
            'name' => $component->name,
            'rate' => $component->rate->percentage(),
            'amount' => $component->amount->toArray(),
            'jurisdiction' => $this->jurisdiction($component->jurisdiction),
            'metadata' => $component->metadata,
        ];
    }

    /** @return array<string, string|null> */
    private function jurisdiction(TaxJurisdiction $jurisdiction): array
    {
        return [
            'identifier' => $jurisdiction->identifier,
            'name' => $jurisdiction->name,
            'country' => $jurisdiction->country,
            'state' => $jurisdiction->state,
            'county' => $jurisdiction->county,
            'city' => $jurisdiction->city,
            'district' => $jurisdiction->district,
        ];
    }
}
