<?php

namespace Larasell\Larasell\Seo;

use InvalidArgumentException;

final class InvalidSeoPath extends InvalidArgumentException
{
    public static function malformed(string $path): self
    {
        return new self("The path [{$path}] is not a valid URL path.");
    }

    public static function reserved(string $path): self
    {
        return new self("The path [{$path}] is reserved.");
    }

    public static function taken(string $path): self
    {
        return new self("The path [{$path}] is already used by another page.");
    }
}
