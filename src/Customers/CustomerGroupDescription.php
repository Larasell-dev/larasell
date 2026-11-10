<?php

namespace Larasell\Larasell\Customers;

use Larasell\Larasell\Customers\Exceptions\InvalidCustomerGroupDescription;
use Stringable;

/** What a customer group is for, as admins read it: not empty, at most 2000 characters. */
final readonly class CustomerGroupDescription implements Stringable
{
    public const MAX_LENGTH = 2000;

    private function __construct(private string $value) {}

    /** Trims the description; throws when nothing is left or it's too long. */
    public static function of(string $description): self
    {
        $description = trim($description);

        if ($description === '' || mb_strlen($description) > self::MAX_LENGTH) {
            throw InvalidCustomerGroupDescription::of($description);
        }

        return new self($description);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
