<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Customers\Actions\AddCustomerContact;
use Larasell\Larasell\Customers\Actions\ChangeCustomerStatus;
use Larasell\Larasell\Customers\Actions\CompanyProfileData;
use Larasell\Larasell\Customers\Actions\CreateCustomer;
use Larasell\Larasell\Customers\Actions\CreateCustomerPayload;
use Larasell\Larasell\Customers\Actions\CreateGuestCustomer;
use Larasell\Larasell\Customers\Actions\CustomerContactPayload;
use Larasell\Larasell\Customers\Actions\LinkCustomerAccount;
use Larasell\Larasell\Customers\Actions\PersonProfileData;
use Larasell\Larasell\Customers\Actions\RemoveCustomerContact;
use Larasell\Larasell\Customers\Actions\UnlinkCustomerAccount;
use Larasell\Larasell\Customers\Actions\UpdateCustomerContact;
use Larasell\Larasell\Customers\CustomerAccountId;
use Larasell\Larasell\Customers\CustomerContactId;
use Larasell\Larasell\Customers\CustomerId;
use Larasell\Larasell\Customers\Enums\CustomerSource;
use Larasell\Larasell\Customers\Enums\CustomerStatus;
use Larasell\Larasell\Customers\Events\CustomerAccountLinked;
use Larasell\Larasell\Customers\Events\CustomerAccountUnlinked;
use Larasell\Larasell\Customers\Events\CustomerContactAdded;
use Larasell\Larasell\Customers\Exceptions\AccountAlreadyLinked;
use Larasell\Larasell\Customers\Exceptions\ContactBelongsToAnotherCustomer;
use Larasell\Larasell\Customers\Exceptions\ContactsNeedACompany;
use Larasell\Larasell\Customers\Exceptions\CustomerNotActive;
use Larasell\Larasell\Customers\HasCustomerAccounts;
use Larasell\Larasell\Customers\Models\Customer;
use Larasell\Larasell\Customers\Models\CustomerAccount;
use Larasell\Larasell\EmailAddress;
use Larasell\Larasell\Enums\Country;

/** The app's own user model: login and passwords stay with the app. */
class CustomerTestUser extends Authenticatable
{
    use HasCustomerAccounts;

    protected $table = 'test_users';

    protected $guarded = [];
}

function companyCustomer(string $name = 'Muster GmbH'): Customer
{
    return app(CreateCustomer::class)->handle(new CreateCustomerPayload(
        profile: new CompanyProfileData(legalName: $name, countryCode: Country::DE),
        source: CustomerSource::Admin,
    ), origin());
}

function contactPayload(string $firstName = 'Max'): CustomerContactPayload
{
    return new CustomerContactPayload(firstName: $firstName, lastName: 'Mustermann', email: EmailAddress::of('max@muster.example'), jobTitle: 'Einkauf');
}

function testUser(string $email = 'max@muster.example'): CustomerTestUser
{
    return CustomerTestUser::query()->create(['email' => $email]);
}

it('adds, updates and removes contacts of a company', function () {
    $company = companyCustomer();
    Event::fake([CustomerContactAdded::class]);

    $max = app(AddCustomerContact::class)->handle(CustomerId::of($company), contactPayload('Max'), origin());
    $anna = app(AddCustomerContact::class)->handle(CustomerId::of($company), contactPayload('Anna'), origin());
    app(UpdateCustomerContact::class)->handle(CustomerContactId::of($anna), new CustomerContactPayload(firstName: 'Anna', lastName: 'Beispiel', jobTitle: 'Buchhaltung'), origin());
    app(RemoveCustomerContact::class)->handle(CustomerContactId::of($max), origin());

    $contacts = $company->fresh()?->contacts;
    expect($contacts)->toHaveCount(1)
        ->and($contacts?->first()?->last_name)->toBe('Beispiel')
        ->and($contacts?->first()?->job_title)->toBe('Buchhaltung')
        ->and($contacts?->first()?->email)->toBeNull();
    Event::assertDispatchedTimes(CustomerContactAdded::class, 2);
});

it('gives contacts only to companies', function () {
    $person = app(CreateGuestCustomer::class)->handle(new PersonProfileData(firstName: 'Erika', lastName: 'Musterfrau'), origin());

    expect(fn () => app(AddCustomerContact::class)->handle(CustomerId::of($person), contactPayload(), origin()))
        ->toThrow(ContactsNeedACompany::class);
});

it('links an app user to a customer and one of its contacts', function () {
    $company = companyCustomer();
    $max = app(AddCustomerContact::class)->handle(CustomerId::of($company), contactPayload(), origin());
    $user = testUser();
    Event::fake([CustomerAccountLinked::class]);

    $account = app(LinkCustomerAccount::class)->handle(CustomerId::of($company), $user, origin(), CustomerContactId::of($max));

    expect($account->account?->is($user))->toBeTrue()
        ->and($account->contact_id)->toBe($max->id)
        ->and($user->customers->pluck('id')->all())->toBe([$company->id])
        ->and($company->accounts()->count())->toBe(1);
    Event::assertDispatched(CustomerAccountLinked::class, fn (CustomerAccountLinked $event): bool => $event->accountId->value === $account->id);
});

it('lets one user work for several companies', function () {
    $user = testUser();

    app(LinkCustomerAccount::class)->handle(CustomerId::of(companyCustomer('Muster GmbH')), $user, origin());
    app(LinkCustomerAccount::class)->handle(CustomerId::of(companyCustomer('Beispiel AG')), $user, origin());

    expect($user->customers()->count())->toBe(2);
});

it('links a user to the same customer only once', function () {
    $company = companyCustomer();
    $user = testUser();
    app(LinkCustomerAccount::class)->handle(CustomerId::of($company), $user, origin());

    expect(fn () => app(LinkCustomerAccount::class)->handle(CustomerId::of($company), $user, origin()))
        ->toThrow(AccountAlreadyLinked::class);
});

it('links no accounts to blocked or archived customers', function () {
    $company = companyCustomer();
    app(ChangeCustomerStatus::class)->handle(CustomerId::of($company), CustomerStatus::Blocked, origin());

    expect(fn () => app(LinkCustomerAccount::class)->handle(CustomerId::of($company), testUser(), origin()))
        ->toThrow(CustomerNotActive::class);
});

it('refuses a contact of another customer', function () {
    $other = app(AddCustomerContact::class)->handle(CustomerId::of(companyCustomer('Beispiel AG')), contactPayload(), origin());

    expect(fn () => app(LinkCustomerAccount::class)->handle(CustomerId::of(companyCustomer()), testUser(), origin(), CustomerContactId::of($other)))
        ->toThrow(ContactBelongsToAnotherCustomer::class);
});

it('keeps the account when its contact is removed', function () {
    $company = companyCustomer();
    $max = app(AddCustomerContact::class)->handle(CustomerId::of($company), contactPayload(), origin());
    $account = app(LinkCustomerAccount::class)->handle(CustomerId::of($company), testUser(), origin(), CustomerContactId::of($max));

    app(RemoveCustomerContact::class)->handle(CustomerContactId::of($max), origin());

    expect($account->fresh()?->contact_id)->toBeNull();
});

it('unlinks an account and leaves the user alone', function () {
    $user = testUser();
    $account = app(LinkCustomerAccount::class)->handle(CustomerId::of(companyCustomer()), $user, origin());
    Event::fake([CustomerAccountUnlinked::class]);

    app(UnlinkCustomerAccount::class)->handle(CustomerAccountId::of($account), origin());

    expect(CustomerAccount::query()->count())->toBe(0)
        ->and($user->fresh())->not->toBeNull();
    Event::assertDispatched(CustomerAccountUnlinked::class);
});
