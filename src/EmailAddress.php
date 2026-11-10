<?php

namespace Larasell\Larasell;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Larasell\Larasell\Casts\StringValueCast;
use Larasell\Larasell\Exceptions\InvalidEmailAddress;
use Stringable;

/** An email address, trimmed and with its domain in lowercase; the local part stays as given. */
final readonly class EmailAddress implements Castable, Stringable
{
    private function __construct(private string $value) {}

    public static function of(string $email): self
    {
        $email = trim($email);
        $at = strrpos($email, '@');

        if ($at === false || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddress::of($email);
        }

        return new self(substr($email, 0, $at + 1).strtolower(substr($email, $at + 1)));
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
