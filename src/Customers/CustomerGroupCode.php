<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Exceptions\InvalidCustomerGroupCode;
use Stringable;

/**
 * How other modules, rules and imports refer to a customer group, e.g. "wholesale": lowercase
 * letters and digits joined by - or _, at most 64 characters.
 */
final readonly class CustomerGroupCode implements Stringable
{
    public const MAX_LENGTH = 64;

    private function __construct(private string $value) {}

    /** Trims the code; throws when it isn't one. */
    public static function of(string $code): self
    {
        $code = trim($code);

        if (strlen($code) > self::MAX_LENGTH || preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $code) !== 1) {
            throw InvalidCustomerGroupCode::of($code);
        }

        return new self($code);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $code): bool
    {
        return $this->value === $code->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
