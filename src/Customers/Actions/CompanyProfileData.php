<?php

namespace Larasell\Larasell\Customers\Actions;

use Larasell\Larasell\Enums\Country;

/** The company a company customer is. Only its legal name and country are required. */
final readonly class CompanyProfileData
{
    public function __construct(
        public string $legalName,
        public Country $countryCode,
        public ?string $vatId = null,
        public ?string $legalForm = null,
        public ?string $companyNumber = null,
        public ?string $industry = null,
        public ?string $website = null,
    ) {}

    /** @return array<string, Country|string|null> */
    public function toAttributes(): array
    {
        return [
            'legal_name' => $this->legalName,
            'country_code' => $this->countryCode,
            'vat_id' => $this->vatId,
            'legal_form' => $this->legalForm,
            'company_number' => $this->companyNumber,
            'industry' => $this->industry,
            'website' => $this->website,
        ];
    }
}
