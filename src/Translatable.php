<?php

namespace Larasell\Larasell;

use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use JsonSerializable;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Settings\LocaleSettings;

final readonly class Translatable implements JsonSerializable
{
    /** @var non-empty-array<string, string> */
    private array $translations;

    /** @param array<array-key, mixed> $translations */
    public function __construct(array $translations)
    {
        $validated = [];

        foreach ($translations as $locale => $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Translations require non-empty locale keys and values.');
            }

            $validated[self::locale((string) $locale)] = $value;
        }

        if ($validated === []) {
            throw new InvalidArgumentException('At least one translation is required.');
        }

        $this->translations = $validated;
    }

    public static function fromString(string $value, ?string $locale = null): self
    {
        return new self([self::locale($locale) => $value]);
    }

    public function get(?string $locale = null): string
    {
        $default = app(LocaleSettings::class)->default()->value;

        foreach (array_unique([...Locale::lookupChain(self::locale($locale)), ...Locale::lookupChain($default)]) as $candidate) {
            if (isset($this->translations[$candidate])) {
                return $this->translations[$candidate];
            }
        }

        return array_values($this->translations)[0];
    }

    /** @return non-empty-array<string, string> */
    public function all(): array
    {
        return $this->translations;
    }

    public function has(string $locale): bool
    {
        return array_key_exists(self::locale($locale), $this->translations);
    }

    public function with(string $locale, string $value): self
    {
        return new self([...$this->translations, self::locale($locale) => $value]);
    }

    public function without(string $locale): ?self
    {
        $translations = $this->translations;
        unset($translations[self::locale($locale)]);

        return $translations === [] ? null : new self($translations);
    }

    /** @return non-empty-array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->all();
    }

    private static function locale(?string $locale): string
    {
        return Locale::canonicalize($locale ?? App::currentLocale());
    }
}
