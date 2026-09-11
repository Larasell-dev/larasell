<?php

namespace Larasell\Larasell\Images;

use InvalidArgumentException;
use JsonSerializable;

/**
 * @phpstan-type PlaceholderPayload array{type: string, value: string, color: string|null}
 */
final readonly class Placeholder implements JsonSerializable
{
    public function __construct(
        public string $type,
        public string $value,
        public ?string $color = null,
    ) {
        if ($type === '' || $value === '') {
            throw new InvalidArgumentException('A placeholder requires a type and a value.');
        }

        if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new InvalidArgumentException('Placeholder color must be a 6-digit hex value.');
        }
    }

    /** @param array<array-key, mixed> $value */
    public static function fromArray(array $value): self
    {
        if (! isset($value['type'], $value['value']) || ! is_string($value['type']) || ! is_string($value['value'])) {
            throw new InvalidArgumentException('A placeholder payload must contain a type and a value.');
        }

        $color = $value['color'] ?? null;

        if ($color !== null && ! is_string($color)) {
            throw new InvalidArgumentException('Placeholder color must be a string or null.');
        }

        return new self($value['type'], $value['value'], $color);
    }

    /** @return PlaceholderPayload */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
            'color' => $this->color === null ? null : strtolower($this->color),
        ];
    }

    /** @return PlaceholderPayload */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
