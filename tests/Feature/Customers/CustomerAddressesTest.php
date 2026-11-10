<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Customers\Actions\AddCustomerAddress;
use Larasell\Larasell\Customers\Actions\CreateGuestCustomer;
use Larasell\Larasell\Customers\Actions\CustomerAddressPayload;
use Larasell\Larasell\Customers\Actions\DeleteCustomerAddress;
use Larasell\Larasell\Customers\Actions\PersonProfileData;
use Larasell\Larasell\Customers\Actions\SetDefaultCustomerAddress;
use Larasell\Larasell\Customers\Actions\UpdateCustomerAddress;
use Larasell\Larasell\Customers\CustomerAddressId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\AddressUse;
use Larasell\Larasell\Customers\Events\CustomerAddressAdded;
use Larasell\Larasell\Customers\Events\CustomerAddressDeleted;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerAddress;
use Larasell\Larasell\Enums\Country;

function addressCustomer(): Customer
{
    return app(CreateGuestCustomer::class)->handle(new PersonProfileData(firstName: 'Erika', lastName: 'Musterfrau'));
}

function addressPayload(string $city = 'Berlin'): CustomerAddressPayload
{
    return new CustomerAddressPayload(
        firstName: 'Erika',
        lastName: 'Musterfrau',
        street: ['Hauptstraße 1', 'Hinterhaus'],
        postcode: '10115',
        city: $city,
        countryCode: Country::DE,
        company: 'Muster GmbH',
    );
}

it('adds addresses to a customer and announces them', function () {
    $customer = addressCustomer();
    Event::fake([CustomerAddressAdded::class]);

    $address = app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload());

    $address = CustomerAddress::query()->findOrFail($address->id);
    expect($address->customer_id)->toBe($customer->id)
        ->and($address->street)->toBe(['Hauptstraße 1', 'Hinterhaus'])
        ->and($address->city)->toBe('Berlin')
        ->and($address->company)->toBe('Muster GmbH');
    Event::assertDispatched(CustomerAddressAdded::class, fn (CustomerAddressAdded $event): bool => $event->addressId->value === $address->id
        && $event->customerId->value === $customer->id);
});

it('makes the first address the default for billing and shipping', function () {
    $customer = addressCustomer();

    $first = app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload('Berlin'));
    app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload('Hamburg'));

    $customer = $customer->fresh();
    expect($customer?->defaultBillingAddress?->id)->toBe($first->id)
        ->and($customer?->defaultShippingAddress?->id)->toBe($first->id);
});

it('moves a default to another address without touching the other default', function () {
    $customer = addressCustomer();
    $berlin = app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload('Berlin'));
    $hamburg = app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload('Hamburg'));

    app(SetDefaultCustomerAddress::class)->handle(CustomerAddressId::of($hamburg), AddressUse::Shipping);

    $customer = $customer->fresh();
    expect($customer?->defaultBillingAddress?->id)->toBe($berlin->id)
        ->and($customer?->defaultShippingAddress?->id)->toBe($hamburg->id)
        ->and(CustomerAddress::query()->where('is_default_shipping', true)->count())->toBe(1);
});

it('updates an address in place', function () {
    $address = app(AddCustomerAddress::class)->handle(CustomerId::of(addressCustomer()), addressPayload('Berlin'));

    app(UpdateCustomerAddress::class)->handle(CustomerAddressId::of($address), addressPayload('München'));

    expect($address->fresh()?->city)->toBe('München')
        ->and($address->fresh()?->is_default_billing)->toBeTrue();
});

it('deletes an address and hands its default to no other address', function () {
    $customer = addressCustomer();
    $berlin = app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload('Berlin'));
    app(AddCustomerAddress::class)->handle(CustomerId::of($customer), addressPayload('Hamburg'));
    Event::fake([CustomerAddressDeleted::class]);

    app(DeleteCustomerAddress::class)->handle(CustomerAddressId::of($berlin));

    $customer = $customer->fresh();
    expect($customer?->addresses)->toHaveCount(1)
        ->and($customer?->defaultBillingAddress)->toBeNull();
    Event::assertDispatched(CustomerAddressDeleted::class);
});
