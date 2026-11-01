<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Larasell\Larasell\Enums\Plan;
use Larasell\Larasell\Exceptions\Licensing\UnknownEntitlement;
use Larasell\Larasell\Facades\Entitlement;
use Larasell\Larasell\Licensing\Commands\RenewLicenseCommand;
use Larasell\Larasell\Licensing\License;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Setting;

/** Signs a lease the way larasell.dev does (LicenseSigningService in the website). */
function signLease(string $secretKey, array $claims = []): string
{
    $encode = fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

    $header = $encode(json_encode(['alg' => 'EdDSA', 'typ' => 'JWT', 'kid' => 'key-1']));
    $payload = $encode(json_encode([
        'iss' => 'larasell',
        'license_id' => 'license-uuid',
        'activation_id' => 'activation-uuid',
        'product' => 'larasell',
        'domain' => 'shop.example.com',
        'plan' => 'pro',
        'iat' => now()->getTimestamp(),
        'exp' => now()->addDays(14)->getTimestamp(),
        ...$claims,
    ]));

    return $header.'.'.$payload.'.'.$encode(sodium_crypto_sign_detached($header.'.'.$payload, $secretKey));
}

function fakeLicenseServer(string $lease): void
{
    Http::fake([
        'larasell.dev/api/v1/licenses/activate' => Http::response([
            'license_id' => 'license-uuid',
            'activation_id' => 'activation-uuid',
            'activation_secret' => 'the-activation-secret',
            'lease' => $lease,
        ], 201),
    ]);
}

beforeEach(function () {
    $keyPair = sodium_crypto_sign_keypair();
    $this->secretKey = sodium_crypto_sign_secretkey($keyPair);

    config(['app.url' => 'https://shop.example.com']);
    $this->app->singleton(License::class, fn ($app) => new License(
        $app->make(ModelRegistry::class),
        base64_encode(sodium_crypto_sign_publickey($keyPair)),
    ));
    $this->app['env'] = 'production';

    Entitlement::define('reviews.moderation', Plan::Free);
    Entitlement::define('admin.audit-log-search', Plan::Pro);
});

it('grants the Pro entitlements after activating a Pro license key', function () {
    expect(Entitlement::active('admin.audit-log-search'))->toBeFalse();

    fakeLicenseServer(signLease($this->secretKey));

    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET'])
        ->expectsOutput('Activated the Pro plan for shop.example.com.')
        ->assertSuccessful();

    expect(Entitlement::plan())->toBe(Plan::Pro)
        ->and(Entitlement::all())->toBe(['reviews.moderation' => true, 'admin.audit-log-search' => true]);

    Http::assertSent(fn (Request $request) => $request['license_key'] === 'LL-ABCDEFGH-SECRET'
        && $request['domain'] === 'shop.example.com'
        && $request['environment'] === 'production'
        && $request['product'] === 'larasell');

    $stored = json_encode(Setting::query()->where('key', 'license')->value('value'));
    expect($stored)->not->toContain('LL-ABCDEFGH-SECRET')->not->toContain('the-activation-secret');
});

it('only grants the Free plan in production without a lease', function () {
    expect(Entitlement::plan())->toBe(Plan::Free)
        ->and(Entitlement::active('reviews.moderation'))->toBeTrue()
        ->and(Entitlement::inactive('admin.audit-log-search'))->toBeTrue();
});

it('reads the plan from LARASELL_PLAN outside production', function (string $environment) {
    $this->app['env'] = $environment;

    config(['larasell.licensing.plan' => 'free']);
    expect(Entitlement::plan())->toBe(Plan::Free)
        ->and(Entitlement::active('admin.audit-log-search'))->toBeFalse();

    config(['larasell.licensing.plan' => 'pro']);
    expect(Entitlement::plan())->toBe(Plan::Pro)
        ->and(Entitlement::active('admin.audit-log-search'))->toBeTrue();
})->with(['local', 'staging', 'testing']);

it('grants Pro outside production when LARASELL_PLAN is not set', function () {
    $this->app['env'] = 'local';

    expect(Entitlement::plan())->toBe(Plan::Pro);
});

it('ignores LARASELL_PLAN and the env driver in production', function () {
    config(['larasell.licensing.driver' => 'env', 'larasell.licensing.plan' => 'pro']);

    expect(Entitlement::plan())->toBe(Plan::Free);
});

it('uses the lease outside production with the lease driver', function () {
    $this->app['env'] = 'staging';
    config(['larasell.licensing.driver' => 'lease', 'larasell.licensing.plan' => 'pro']);

    expect(Entitlement::plan())->toBe(Plan::Free);

    fakeLicenseServer(signLease($this->secretKey));
    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET'])->assertSuccessful();

    expect(Entitlement::plan())->toBe(Plan::Pro);
    Http::assertSent(fn (Request $request) => $request['environment'] === 'staging');
});

it('refuses leases that larasell.dev did not sign for this shop', function (Closure $lease) {
    fakeLicenseServer($lease($this->secretKey));

    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET'])
        ->expectsOutput('The lease from larasell.dev is not valid for this shop.')
        ->assertFailed();

    expect(Entitlement::plan())->toBe(Plan::Free);
})->with([
    'signed with another key' => [fn () => signLease(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()))],
    'for another domain' => [fn (string $key) => signLease($key, ['domain' => 'other-shop.example.com'])],
    'already expired' => [fn (string $key) => signLease($key, ['exp' => now()->subSecond()->getTimestamp()])],
    'with the plan changed after signing' => [function (string $key) {
        [$header, , $signature] = explode('.', signLease($key, ['plan' => 'free']));
        [, $payload] = explode('.', signLease($key));

        return "{$header}.{$payload}.{$signature}";
    }],
]);

it('reports why larasell.dev refused the key', function () {
    Http::fake(['*' => Http::response(['error' => 'domain_not_authorized'], 403)]);

    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET'])
        ->expectsOutput('larasell.dev refused the license: domain_not_authorized.')
        ->expectsOutput('Add the domain to the license on your larasell.dev dashboard, or correct APP_URL.')
        ->assertFailed();
});

it('keeps the Pro plan through the grace period after the license expires', function () {
    fakeLicenseServer(signLease($this->secretKey));
    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET']);

    Http::fake(['*' => Http::response(['error' => 'license_expired'], 403)]);
    $this->travel(13)->days();
    $this->artisan(RenewLicenseCommand::class)->assertFailed();

    expect(Entitlement::plan())->toBe(Plan::Pro);

    $this->travel(2)->days();

    expect(Entitlement::plan())->toBe(Plan::Free);
});

it('renews the lease with the stored activation', function () {
    fakeLicenseServer(signLease($this->secretKey));
    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET']);

    $this->travel(10)->days();
    Http::fake(['larasell.dev/api/v1/licenses/renew' => Http::response([
        'license_id' => 'license-uuid',
        'activation_id' => 'activation-uuid',
        'lease' => signLease($this->secretKey),
    ])]);
    $this->artisan(RenewLicenseCommand::class)->assertSuccessful();
    $this->travel(10)->days();

    expect(Entitlement::plan())->toBe(Plan::Pro);
    Http::assertSent(fn (Request $request) => $request['activation_id'] === 'activation-uuid'
        && $request['activation_secret'] === 'the-activation-secret');
});

it('drops the Pro plan at once when the license is revoked', function () {
    fakeLicenseServer(signLease($this->secretKey));
    $this->artisan('larasell:license:activate', ['key' => 'LL-ABCDEFGH-SECRET']);

    Http::fake(['*' => Http::response(['error' => 'license_revoked'], 403)]);
    $this->artisan(RenewLicenseCommand::class)->assertFailed();

    expect(Entitlement::plan())->toBe(Plan::Free);
});

it('fails loudly on an entitlement that is not defined', function () {
    try {
        Entitlement::active('admin.audit-log-serach');
        $this->fail('Checking an undefined entitlement did not throw.');
    } catch (UnknownEntitlement $exception) {
        expect($exception->getMessage())->toBe('The entitlement [admin.audit-log-serach] is not defined.')
            ->and($exception->errorCode())->toBe('larasell.licensing.unknown-entitlement');
    }
});

it('lets tests pretend to have a plan', function () {
    Entitlement::fake(Plan::Pro);

    expect(Entitlement::active('admin.audit-log-search'))->toBeTrue();
});

it('only renews the lease on schedule when the lease driver is in use', function () {
    $renew = fn () => collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'larasell:license:renew'))
        ->filtersPass($this->app);

    expect($renew())->toBeTrue();

    $this->app['env'] = 'local';

    expect($renew())->toBeFalse();
});
