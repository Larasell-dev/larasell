<?php

namespace Larasell\Larasell\Media;

use Illuminate\Contracts\Encryption\StringEncrypter;
use Larasell\Larasell\Models\Setting;

/**
 * The Mux account saved in the panel, which overrides larasell.media.providers.mux where it has a
 * value; what was never saved still comes from the config file. Secrets are stored encrypted with
 * the app key and only leave this class for MediaProviders, never for a page.
 */
final class MuxSettings
{
    public const KEY = 'media.mux';

    public const PROVIDER = 'mux';

    /** The values that are stored encrypted, and that a page only learns are set. */
    public const SECRETS = ['token_secret', 'webhook_secret', 'signing_key'];

    public function __construct(private readonly StringEncrypter $encrypter) {}

    public function tokenId(): ?string
    {
        return $this->stored()['token_id'] ?? null;
    }

    public function signingKeyId(): ?string
    {
        return $this->stored()['signing_key_id'] ?? null;
    }

    /** Whether new videos go to Mux; null when never saved, so the config file decides. */
    public function useForVideos(): ?bool
    {
        $value = $this->stored()['use_for_videos'] ?? null;

        return is_bool($value) ? $value : null;
    }

    /** Whether the secret was saved in the panel, e.g. "token_secret". */
    public function has(string $secret): bool
    {
        return ($this->stored()[$secret] ?? '') !== '';
    }

    /** @return array<string, string> the saved values, decrypted, to lay over the config file's */
    public function overrides(): array
    {
        $values = [];

        foreach ($this->stored() as $key => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }
            $values[$key] = in_array($key, self::SECRETS, true) ? $this->encrypter->decryptString($value) : $value;
        }

        return $values;
    }

    /**
     * Saves the values; a null secret keeps the saved one.
     *
     * @param  array{token_id: string, signing_key_id: ?string, use_for_videos: bool, token_secret: ?string, webhook_secret: ?string, signing_key: ?string}  $values
     */
    public function save(array $values): void
    {
        $stored = $this->stored();

        foreach ($values as $key => $value) {
            if (in_array($key, self::SECRETS, true)) {
                if ($value !== null && $value !== '') {
                    $stored[$key] = $this->encrypter->encryptString((string) $value);
                }

                continue;
            }
            $stored[$key] = $value;
        }

        Setting::query()->updateOrCreate(['key' => self::KEY], ['value' => $stored]);
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $value = Setting::query()->where('key', self::KEY)->value('value');

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }
}
