<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Larasell\Larasell\Contracts\StorefrontUser as StorefrontUserContract;
use Larasell\Larasell\Customers\StorefrontUser;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Customer;
use LogicException;

class TestUser extends Authenticatable implements StorefrontUserContract
{
    use StorefrontUser;

    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    config()->set('auth.providers.users.model', TestUser::class);

    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password')->nullable();
        $table->timestamps();
    });
});

it('creates a customer from a storefront user and links them', function () {
    $user = TestUser::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $customer = $user->createCustomer();

    expect($customer->first_name)->toBe('Ada')
        ->and($customer->last_name)->toBe('Lovelace')
        ->and($customer->fullName())->toBe('Ada Lovelace')
        ->and($user->customer?->is($customer))->toBeTrue()
        ->and($customer->users()->pluck('id')->all())->toBe([$user->getKey()]);
});

it('allows several users on one customer, but not several customers on one user', function () {
    $ada = TestUser::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $grace = TestUser::query()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $customer = $ada->createCustomer();
    $customer->users()->attach($grace->getKey());

    expect($ada->customer?->is($customer))->toBeTrue()
        ->and($grace->fresh()->customer?->is($customer))->toBeTrue()
        ->and($customer->users()->pluck('id')->sort()->values()->all())->toBe([
            $ada->getKey(),
            $grace->getKey(),
        ]);

    expect(fn () => $ada->createCustomer())->toThrow(LogicException::class);
});

it('attaches carts to the authenticatable user, not the customer', function () {
    $user = TestUser::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);
    $user->createCustomer();

    $cart = Cart::query()->create([
        'currency' => Currency::EUR,
        'user_id' => $user->getKey(),
    ]);

    expect($user->carts()->pluck('id')->all())->toBe([$cart->getKey()]);
});

it('lists orders for a customer', function () {
    $customer = Customer::query()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    expect($customer->orders()->count())->toBe(0);
});
