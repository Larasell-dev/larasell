<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Contracts\CustomerNumberGenerator;
use Larasell\Larasell\Customers\Actions\ChangeCustomerStatus;
use Larasell\Larasell\Customers\Actions\CompanyProfileData;
use Larasell\Larasell\Customers\Actions\CreateCustomer;
use Larasell\Larasell\Customers\Actions\CreateCustomerPayload;
use Larasell\Larasell\Customers\Actions\CreateGuestCustomer;
use Larasell\Larasell\Customers\Actions\PersonProfileData;
use Larasell\Larasell\Customers\Actions\UpdateCustomer;
use Larasell\Larasell\Customers\Actions\UpdateCustomerPayload;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\CustomerNumbers\SequentialCustomerNumberGenerator;
use Larasell\Larasell\Customers\Enums\CustomerSource;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use Larasell\Larasell\Customers\Enums\CustomerType;
use Larasell\Larasell\Customers\Events\CustomerCreated;
use Larasell\Larasell\Customers\Events\CustomerStatusChanged;
use Larasell\Larasell\Customers\Events\CustomerUpdated;
use Larasell\Larasell\Customers\Exceptions\ProfileDoesNotMatchType;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\EmailAddress;
use Larasell\Larasell\Enums\Country;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\PhoneNumber;
use Larasell\Larasell\Url;
use Larasell\Larasell\VatId;

it('creates an individual customer from a person profile', function () {
    Event::fake([CustomerCreated::class]);

    $customer = app(CreateCustomer::class)->handle(new CreateCustomerPayload(
        profile: new PersonProfileData(firstName: 'Erika', lastName: 'Musterfrau', email: EmailAddress::of('erika@example.com'), phone: PhoneNumber::of('+49 30 123')),
        source: CustomerSource::Pos,
        preferredLocale: Locale::GERMAN_GERMANY,
        preferredCurrency: Currency::EUR,
    ));

    $customer = Customer::query()->findOrFail($customer->id);
    expect($customer->type)->toBe(CustomerType::Individual)
        ->and($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->source)->toBe(CustomerSource::Pos)
        ->and($customer->preferred_locale)->toBe(Locale::GERMAN_GERMANY)
        ->and($customer->preferred_currency)->toBe(Currency::EUR)
        ->and($customer->personProfile?->last_name)->toBe('Musterfrau')
        ->and($customer->personProfile?->email)->toEqual(EmailAddress::of('erika@example.com'))
        ->and($customer->personProfile?->phone?->value())->toBe('+49 30 123')
        ->and($customer->companyProfile)->toBeNull()
        ->and($customer->displayName())->toBe('Erika Musterfrau');
    Event::assertDispatched(CustomerCreated::class, fn (CustomerCreated $event): bool => $event->customerId->value === $customer->id);
});

it('creates a company customer from a company profile', function () {
    $customer = app(CreateCustomer::class)->handle(new CreateCustomerPayload(
        profile: new CompanyProfileData(legalName: 'Muster GmbH', countryCode: Country::DE, vatId: VatId::of('DE 123 456 789'), legalForm: 'GmbH'),
        source: CustomerSource::Admin,
    ));

    expect($customer->type)->toBe(CustomerType::Company)
        ->and($customer->companyProfile?->vat_id)->toEqual(VatId::of('DE123456789'))
        ->and($customer->companyProfile?->legal_form)->toBe('GmbH')
        ->and($customer->personProfile)->toBeNull()
        ->and($customer->displayName())->toBe('Muster GmbH');
});

it('numbers customers in sequence by default', function () {
    $numbers = [
        app(SequentialCustomerNumberGenerator::class)->generate(),
        app(SequentialCustomerNumberGenerator::class)->generate(),
    ];

    expect($numbers)->toBe(['C-000001', 'C-000002']);
});

it('stores the number returned by a custom generator', function () {
    app()->bind(CustomerNumberGenerator::class, fn () => new class implements CustomerNumberGenerator
    {
        public function generate(): string
        {
            return 'K-7';
        }
    });

    $customer = app(CreateCustomer::class)->handle(new CreateCustomerPayload(
        profile: new PersonProfileData(firstName: 'A', lastName: 'B'),
        source: CustomerSource::Import,
    ));

    expect($customer->customer_number)->toBe('K-7');
});

it('creates a new guest customer every time, even for a known email', function () {
    $profile = new PersonProfileData(firstName: 'Erika', lastName: 'Musterfrau', email: EmailAddress::of('erika@example.com'));

    $first = app(CreateGuestCustomer::class)->handle($profile);
    $second = app(CreateGuestCustomer::class)->handle($profile);

    expect(CustomerId::of($first))->not->toEqual(CustomerId::of($second))
        ->and($first->customer_number)->not->toBe($second->customer_number)
        ->and($first->source)->toBe(CustomerSource::Storefront)
        ->and($first->type)->toBe(CustomerType::Individual)
        ->and($first->accounts()->count())->toBe(0);
});

it('updates the profile and preferences of a customer', function () {
    $customer = app(CreateCustomer::class)->handle(new CreateCustomerPayload(
        profile: new CompanyProfileData(legalName: 'Muster GmbH', countryCode: Country::DE),
        source: CustomerSource::Admin,
    ));
    Event::fake([CustomerUpdated::class]);

    app(UpdateCustomer::class)->handle(CustomerId::of($customer), new UpdateCustomerPayload(
        profile: new CompanyProfileData(legalName: 'Muster AG', countryCode: Country::AT, website: Url::of('muster.example')),
        preferredLocale: Locale::GERMAN_AUSTRIA,
    ));

    $customer = Customer::query()->findOrFail($customer->id);
    expect($customer->companyProfile?->legal_name)->toBe('Muster AG')
        ->and($customer->companyProfile?->country_code)->toBe(Country::AT)
        ->and($customer->companyProfile?->website)->toEqual(Url::of('https://muster.example'))
        ->and($customer->preferred_locale)->toBe(Locale::GERMAN_AUSTRIA);
    Event::assertDispatched(CustomerUpdated::class, fn (CustomerUpdated $event): bool => $event->customerId->value === $customer->id);
});

it('refuses a profile of the other type', function () {
    $customer = app(CreateCustomer::class)->handle(new CreateCustomerPayload(
        profile: new PersonProfileData(firstName: 'Erika', lastName: 'Musterfrau'),
        source: CustomerSource::Admin,
    ));

    expect(fn () => app(UpdateCustomer::class)->handle(CustomerId::of($customer), new UpdateCustomerPayload(
        profile: new CompanyProfileData(legalName: 'Muster GmbH', countryCode: Country::DE),
    )))->toThrow(ProfileDoesNotMatchType::class);

    expect($customer->fresh()?->companyProfile)->toBeNull();
});

it('blocks, archives and reactivates a customer and announces each change', function () {
    $customer = app(CreateGuestCustomer::class)->handle(new PersonProfileData(firstName: 'A', lastName: 'B'));
    Event::fake([CustomerStatusChanged::class]);

    app(ChangeCustomerStatus::class)->handle(CustomerId::of($customer), CustomerStatus::Blocked);
    app(ChangeCustomerStatus::class)->handle(CustomerId::of($customer), CustomerStatus::Archived);
    app(ChangeCustomerStatus::class)->handle(CustomerId::of($customer), CustomerStatus::Active);
    app(ChangeCustomerStatus::class)->handle(CustomerId::of($customer), CustomerStatus::Active);

    expect($customer->fresh()?->status)->toBe(CustomerStatus::Active);
    Event::assertDispatchedTimes(CustomerStatusChanged::class, 3);
    Event::assertDispatched(CustomerStatusChanged::class, fn (CustomerStatusChanged $event): bool => $event->from === CustomerStatus::Blocked
        && $event->to === CustomerStatus::Archived);
});
