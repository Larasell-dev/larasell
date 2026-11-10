<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Customers\Actions\AddCustomerToGroup;
use Larasell\Larasell\Customers\Actions\CreateCustomerGroup;
use Larasell\Larasell\Customers\Actions\CreateGuestCustomer;
use Larasell\Larasell\Customers\Actions\CustomerGroupPayload;
use Larasell\Larasell\Customers\Actions\DeleteCustomerGroup;
use Larasell\Larasell\Customers\Actions\PersonProfileData;
use Larasell\Larasell\Customers\Actions\RemoveCustomerFromGroup;
use Larasell\Larasell\Customers\Actions\UpdateCustomerGroup;
use Larasell\Larasell\Customers\CustomerGroupCode;
use Larasell\Larasell\Customers\CustomerGroupDescription;
use Larasell\Larasell\Customers\CustomerGroupId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Events\CustomerJoinedGroup;
use Larasell\Larasell\Customers\Events\CustomerLeftGroup;
use Larasell\Larasell\Customers\Exceptions\CustomerGroupInUse;
use Larasell\Larasell\Customers\Exceptions\DuplicateCustomerGroupCode;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerGroup;
use Larasell\Larasell\Translatable;

function group(string $code, string $name = 'Wholesale'): CustomerGroup
{
    return app(CreateCustomerGroup::class)->handle(new CustomerGroupPayload(code: CustomerGroupCode::of($code), name: new Translatable(['en' => $name])));
}

function groupCustomer(): Customer
{
    return app(CreateGuestCustomer::class)->handle(new PersonProfileData(firstName: 'A', lastName: 'B'));
}

it('creates and updates groups with unique codes', function () {
    $wholesale = group('wholesale');

    app(UpdateCustomerGroup::class)->handle(CustomerGroupId::of($wholesale), new CustomerGroupPayload(
        code: CustomerGroupCode::of('wholesale-premium'),
        name: new Translatable(['en' => 'Wholesale Premium']),
        description: CustomerGroupDescription::of('Our biggest resellers'),
    ));

    $wholesale = CustomerGroup::query()->findOrFail($wholesale->id);
    expect($wholesale->code)->toBe('wholesale-premium')
        ->and($wholesale->name->get('en'))->toBe('Wholesale Premium')
        ->and($wholesale->description)->toBe('Our biggest resellers');
    expect(fn () => group('wholesale-premium'))->toThrow(DuplicateCustomerGroupCode::class);
});

it('allows a group to keep its own code on update', function () {
    $vip = group('vip', 'VIP');

    app(UpdateCustomerGroup::class)->handle(CustomerGroupId::of($vip), new CustomerGroupPayload(code: CustomerGroupCode::of('vip'), name: new Translatable(['en' => 'Very important'])));

    expect($vip->fresh()?->name->get('en'))->toBe('Very important');
});

it('puts a customer in several groups and takes it out again', function () {
    $customer = groupCustomer();
    $retail = group('retail', 'Retail');
    $vip = group('vip', 'VIP');
    Event::fake([CustomerJoinedGroup::class, CustomerLeftGroup::class]);

    app(AddCustomerToGroup::class)->handle(CustomerId::of($customer), CustomerGroupId::of($retail));
    app(AddCustomerToGroup::class)->handle(CustomerId::of($customer), CustomerGroupId::of($vip));
    app(AddCustomerToGroup::class)->handle(CustomerId::of($customer), CustomerGroupId::of($vip));
    app(RemoveCustomerFromGroup::class)->handle(CustomerId::of($customer), CustomerGroupId::of($retail));

    expect($customer->fresh()?->groups->pluck('code')->all())->toBe(['vip']);
    Event::assertDispatchedTimes(CustomerJoinedGroup::class, 2);
    Event::assertDispatchedTimes(CustomerLeftGroup::class, 1);
});

it('deletes only groups without members', function () {
    $empty = group('empty');
    $used = group('used');
    app(AddCustomerToGroup::class)->handle(CustomerId::of(groupCustomer()), CustomerGroupId::of($used));

    app(DeleteCustomerGroup::class)->handle(CustomerGroupId::of($empty));

    expect($empty->fresh())->toBeNull();
    expect(fn () => app(DeleteCustomerGroup::class)->handle(CustomerGroupId::of($used)))->toThrow(CustomerGroupInUse::class);
});
