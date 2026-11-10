<?php

namespace Larasell\Larasell;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Larasell\Larasell\Casts\StringValueCast;
use Larasell\Larasell\Exceptions\InvalidPhoneNumber;
use Stringable;

/**
 * A phone number as it was typed, e.g. "+49 (0)30 123-456", only trimmed. It isn't normalised,
 * so two ways of writing one number aren't equal.
 */
final readonly class PhoneNumber implements Castable, Stringable
{
    public const MAX_LENGTH = 32;

    private function __construct(private string $value) {}

    /** Digits, spaces and + ( ) - / . with at least three digits. */
    public static function of(string $phone): self
    {
        $phone = trim($phone);

        if (strlen($phone) > self::MAX_LENGTH
            || preg_match('#^\+?[0-9 ()\-/.]+$#', $phone) !== 1
            || preg_match_all('/[0-9]/', $phone) < 3) {
            throw InvalidPhoneNumber::of($phone);
        }

        return new self($phone);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** @param array<mixed> $arguments */
    public static function castUsing(array $arguments): StringValueCast
    {
        return new StringValueCast(self::class);
    }
}
