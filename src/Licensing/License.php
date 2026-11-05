<?php

namespace Larasell\Larasell\Licensing;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Larasell\Larasell\Exceptions\Licensing\LicenseException;
use Larasell\Larasell\Models\Setting;

/**
 * This installation's Larasell license: activates a license key with larasell.dev, renews
 * the signed lease and reads it back. The activation and the lease live in larasell_settings;
 * the license key itself is never stored.
 */
final class License
{
    private const SETTING = 'license';

    private const API_URL = 'https://larasell.dev/api/v1/licenses';

    /**
     * The base64-encoded Ed25519 key larasell.dev signs leases with. Hard-coded rather than
     * configurable: a shop that could pick the key could sign its own Pro lease.
     */
    private const PUBLIC_KEY = 'kutEbLkaS3RXdsAA00IjLcwJPiAkOq+4GgtZmCvw1kg=';

    /** Denials after which the current lease must not grant anything any more. */
    private const REVOKING_ERRORS = ['larasell.licensing.invalid-activation', 'larasell.licensing.license-revoked', 'larasell.licensing.license-suspended'];

    /**
     * @param  string  $publicKey  only for tests, which sign leases with a key of their own
     */
    public function __construct(
        private readonly string $publicKey = self::PUBLIC_KEY,
    ) {}

    /**
     * @throws LicenseException when larasell.dev refuses the key, e.g. for an unknown domain
     */
    public function activate(string $key): Lease
    {
        $response = $this->request('activate', [
            'license_key' => $key,
            'installation_id' => $this->installationId(),
            'domain' => $this->domain(),
            'environment' => match (true) {
                app()->isProduction() => 'production',
                app()->environment('staging') => 'staging',
                default => 'development',
            },
            'product' => 'larasell',
        ]);

        $stored = $this->stored();
        $lease = $this->verified($response->json('lease'));

        $this->store([
            'installation_id' => $this->installationId(),
            'activation_id' => $response->json('activation_id'),
            // Only a new activation gets a secret; activating the same installation again keeps it.
            'activation_secret' => is_string($response->json('activation_secret'))
                ? Crypt::encryptString($response->json('activation_secret'))
                : $stored['activation_secret'] ?? null,
            'lease' => $response->json('lease'),
        ]);

        return $lease;
    }

    /**
     * Exchanges the activation for a fresh lease.
     *
     * @throws LicenseException when the installation is not activated or larasell.dev refuses
     */
    public function renew(): Lease
    {
        $stored = $this->stored();

        if (! isset($stored['activation_id'], $stored['activation_secret'])) {
            throw LicenseException::notActivated();
        }

        try {
            $response = $this->request('renew', [
                'activation_id' => $stored['activation_id'],
                'activation_secret' => Crypt::decryptString($stored['activation_secret']),
            ]);
        } catch (LicenseException $exception) {
            // An expired license keeps its last lease, which runs out at the end of the grace period.
            if (in_array($exception->errorCode(), self::REVOKING_ERRORS, true)) {
                $this->store([...$stored, 'lease' => null]);
            }

            throw $exception;
        }

        $lease = $this->verified($response->json('lease'));
        $this->store([...$stored, 'lease' => $response->json('lease')]);

        return $lease;
    }

    /** The current lease, or null when there is none or it is no longer valid. */
    public function lease(): ?Lease
    {
        $token = $this->stored()['lease'] ?? null;

        return is_string($token) ? Lease::verify($token, $this->publicKey, $this->domain()) : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws LicenseException
     */
    private function request(string $endpoint, array $payload): Response
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->post(self::API_URL.'/'.$endpoint, $payload);

        if ($response->failed()) {
            $error = $response->json('error');

            throw is_string($error) ? LicenseException::refused($error) : LicenseException::unavailable($response->status());
        }

        return $response;
    }

    private function verified(mixed $token): Lease
    {
        $lease = is_string($token) ? Lease::verify($token, $this->publicKey, $this->domain()) : null;

        return $lease ?? throw LicenseException::invalidLease();
    }

    private function domain(): string
    {
        return strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    private function installationId(): string
    {
        $stored = $this->stored();

        if (! isset($stored['installation_id'])) {
            $stored['installation_id'] = (string) Str::uuid();
            $this->store($stored);
        }

        return $stored['installation_id'];
    }

    /** @return array{installation_id?: string, activation_id?: string, activation_secret?: string, lease?: string|null} */
    private function stored(): array
    {
        /** @var array{installation_id?: string, activation_id?: string, activation_secret?: string, lease?: string|null} */
        return Setting::query()->where('key', self::SETTING)->first()?->getAttribute('value') ?? [];
    }

    /** @param array<string, mixed> $value */
    private function store(array $value): void
    {
        Setting::query()->updateOrCreate(['key' => self::SETTING], ['value' => $value]);
    }
}
