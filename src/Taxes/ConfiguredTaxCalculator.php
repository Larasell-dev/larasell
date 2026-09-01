<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Contracts\TaxCalculator;
use Larasell\Larasell\Contracts\TaxJurisdictionResolver;
use Larasell\Larasell\Contracts\TaxRateResolver;
use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Enums\TaxTreatment;
use Larasell\Larasell\Price;

final readonly class ConfiguredTaxCalculator implements TaxCalculator
{
    public function __construct(
        private TaxJurisdictionResolver $jurisdictions,
        private TaxRateResolver $rates,
        private TaxAmountCalculator $amounts = new TaxAmountCalculator,
    ) {}

    public function calculate(TaxCalculationContext $context): TaxResult
    {
        $resolution = $this->jurisdictions->resolve($context);

        if ($resolution->status === TaxResultStatus::Unavailable || $resolution->jurisdiction === null) {
            return TaxResult::unavailable($context->priceMode, $resolution->reason ?? 'Tax jurisdiction is unavailable.');
        }

        $lines = [];

        foreach ($context->lines as $line) {
            $rule = $this->rates->resolve($line->category, $resolution->jurisdiction);

            if ($rule === null) {
                return TaxResult::unavailable(
                    $context->priceMode,
                    "No tax rule is configured for category [{$line->category}] in jurisdiction [{$resolution->jurisdiction->identifier}].",
                );
            }

            $lines[] = $this->calculateLine($line, $rule, $resolution->jurisdiction, $context->priceMode);
        }

        if ($resolution->status === TaxResultStatus::Provisional) {
            return TaxResult::provisional(
                $context->priceMode,
                $lines,
                $resolution->reason ?? 'The tax jurisdiction is provisional.',
                $resolution->jurisdiction,
            );
        }

        return TaxResult::calculated($context->priceMode, $lines, $resolution->jurisdiction);
    }

    private function calculateLine(TaxableLine $line, TaxRateRule $rule, TaxJurisdiction $jurisdiction, TaxPriceMode $priceMode): TaxLineResult
    {
        $discountedAmount = $line->discountedAmount();

        if ($rule->treatment !== TaxTreatment::Taxable) {
            return new TaxLineResult(
                $line->identifier,
                $line->category,
                $rule->treatment,
                $rule->treatment === TaxTreatment::ZeroRated ? $discountedAmount : Price::of(0),
                Price::of(0),
                discountAmount: $line->discountAmount,
                amount: $discountedAmount,
            );
        }

        $taxAmount = $this->amounts->calculate($discountedAmount, $rule->rate, $priceMode);
        $taxableAmount = $priceMode === TaxPriceMode::Inclusive
            ? $discountedAmount->subtract($taxAmount)
            : $discountedAmount;
        $component = new TaxComponent(
            $rule->identifier,
            $rule->name,
            $rule->rate,
            $taxAmount,
            $jurisdiction,
        );

        return new TaxLineResult(
            $line->identifier,
            $line->category,
            $rule->treatment,
            $taxableAmount,
            $taxAmount,
            [$component],
            $line->discountAmount,
            $discountedAmount,
        );
    }
}
