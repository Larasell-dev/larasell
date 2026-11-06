<?php

namespace Larasell\Larasell\Seo;

use InvalidArgumentException;

final class InvalidSeoPath extends InvalidArgumentException
{
    public const MALFORMED = 'malformed';

    public const RESERVED = 'reserved';

    public const TAKEN = 'taken';

    /** @param  self::MALFORMED|self::RESERVED|self::TAKEN  $reason  Why, e.g. to tell the user in their language */
    private function __construct(string $message, public readonly string $reason, public readonly string $path)
    {
        parent::__construct($message);
    }

    public static function malformed(string $path): self
    {
        return new self("The path [{$path}] is not a valid URL path.", self::MALFORMED, $path);
    }

    public static function reserved(string $path): self
    {
        return new self("The path [{$path}] is reserved.", self::RESERVED, $path);
    }

    public static function taken(string $path): self
    {
        return new self("The path [{$path}] is already used by another page.", self::TAKEN, $path);
    }
}
