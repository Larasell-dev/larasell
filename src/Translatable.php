<?php

namespace Larasell\Larasell;

use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use JsonSerializable;

final readonly class Translatable implements JsonSerializable
{
    /** @var non-empty-array<string, string> */
    private array $translations;

    /** @param non-empty-array<string, string> $translations */
    public function __construct(array $translations)
    {
        $validated = [];

        foreach ($translations as $locale => $value) {
            $locale = (string) $locale;

            if (! self::isValidLocale($locale) || ! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException('Translations require non-empty locale keys and values.');
            }

            $validated[$locale] = $value;
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
        $locale = self::locale($locale);
        $fallback = self::locale((string) config('app.fallback_locale', 'en'));

        foreach (array_unique([$locale, self::baseLocale($locale), $fallback, self::baseLocale($fallback)]) as $candidate) {
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
        $locale = $locale ?? App::currentLocale();

        if (! self::isValidLocale($locale)) {
            throw new InvalidArgumentException("Invalid locale [{$locale}].");
        }

        return $locale;
    }

    private static function baseLocale(string $locale): string
    {
        return explode('_', $locale, 2)[0];
    }

    private static function isValidLocale(string $locale): bool
    {
        return preg_match('/^[a-z]{2,3}(?:_(?:[A-Z]{2}|[0-9]{3}))?$/D', $locale) === 1;
    }
}
