<?php

namespace Larasell\Larasell\RichText;

use JsonSerializable;
use Larasell\Larasell\Translatable;

/**
 * Rich text in one or more locales, e.g. a product's description. Reading a locale falls back
 * like Translatable does.
 */
final readonly class TranslatableRichText implements JsonSerializable
{
    /** The documents as JSON, so Translatable's locale handling and fallback apply. */
    private Translatable $documents;

    /**
     * @param  array<string, RichText>  $translations  At least one, as Translatable requires.
     */
    public function __construct(array $translations)
    {
        $this->documents = new Translatable(array_map(fn (RichText $text): string => $text->toJson(), $translations));
    }

    public static function of(RichText $text, ?string $locale = null): self
    {
        return self::fromTranslatable(Translatable::fromString($text->toJson(), $locale));
    }

    /**
     * Reads stored translations, each a Portable Text document as JSON.
     */
    public static function fromTranslatable(Translatable $stored): self
    {
        return new self(array_map(RichText::fromJson(...), $stored->all()));
    }

    public function get(?string $locale = null): RichText
    {
        return RichText::fromJson($this->documents->get($locale));
    }

    public function has(string $locale): bool
    {
        return $this->documents->has($locale);
    }

    /** With the locale's text, or without the locale when the text is empty. */
    public function with(string $locale, RichText $text): ?self
    {
        if ($text->isEmpty()) {
            return $this->without($locale);
        }

        return self::fromTranslatable($this->documents->with($locale, $text->toJson()));
    }

    public function without(string $locale): ?self
    {
        $documents = $this->documents->without($locale);

        return $documents === null ? null : self::fromTranslatable($documents);
    }

    /** @return non-empty-array<string, RichText> */
    public function all(): array
    {
        return array_map(RichText::fromJson(...), $this->documents->all());
    }

    /** The documents as JSON strings, keyed by locale, as they are stored. */
    public function toTranslatable(): Translatable
    {
        return $this->documents;
    }

    /** @return non-empty-array<string, list<array<string, mixed>>> */
    public function jsonSerialize(): array
    {
        return array_map(fn (RichText $text): array => $text->toArray(), $this->all());
    }
}
