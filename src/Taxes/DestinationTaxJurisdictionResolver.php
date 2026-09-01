<?php

namespace Larasell\Larasell\Taxes;

use Larasell\Larasell\Address;
use Larasell\Larasell\Contracts\TaxJurisdictionResolver;

final class DestinationTaxJurisdictionResolver implements TaxJurisdictionResolver
{
    public function resolve(TaxCalculationContext $context): TaxJurisdictionResolution
    {
        if ($context->shippingAddress !== null) {
            return TaxJurisdictionResolution::calculated($this->fromAddress($context->shippingAddress));
        }

        if ($context->billingAddress !== null) {
            return TaxJurisdictionResolution::provisional(
                $this->fromAddress($context->billingAddress),
                'The billing address was used because no shipping address was available.',
            );
        }

        return TaxJurisdictionResolution::unavailable('A shipping or billing address is required to resolve tax jurisdiction.');
    }

    private function fromAddress(Address $address): TaxJurisdiction
    {
        $country = strtoupper($address->country);
        $state = $address->state === null ? null : strtoupper($address->state);
        $identifier = $state === null ? $country : $country.'-'.$state;

        return new TaxJurisdiction(
            identifier: $identifier,
            name: $identifier,
            country: $country,
            state: $state,
            city: $address->city,
        );
    }
}
