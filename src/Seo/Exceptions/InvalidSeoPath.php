<?php

namespace Larasell\Larasell\Seo\Exceptions;

use InvalidArgumentException;
use Larasell\Chronicle\Exceptions\HasStructuredFields;
use Larasell\Chronicle\Exceptions\StructuredException;

final class InvalidSeoPath extends InvalidArgumentException implements StructuredException
{
    use HasStructuredFields;

    public const MALFORMED = 'malformed';

    public const RESERVED = 'reserved';

    public const TAKEN = 'taken';

    /** @param  self::MALFORMED|self::RESERVED|self::TAKEN  $reason  Why, e.g. to tell the user in their language */
    private function __construct(string $message, public readonly string $reason, public readonly string $path, string $why, string $fix)
    {
        parent::__construct($message);

        $this->withError("larasell.seo.{$reason}-path", $why, $fix);
    }

    public static function malformed(string $path): self
    {
        return new self(
            "The path [{$path}] is not a valid URL path.", self::MALFORMED, $path,
            why: 'A path is matched against request URLs, so it cannot hold a query string, a fragment or spaces.',
            fix: 'Use lowercase words joined by -, separated by /, e.g. p/winter-boots.',
        );
    }

    public static function reserved(string $path): self
    {
        return new self(
            "The path [{$path}] is reserved.", self::RESERVED, $path,
            why: 'Routes of the shop or the app already answer this path.',
            fix: 'Pick another path, or remove it from larasell.seo.reserved_paths.',
        );
    }

    public static function taken(string $path): self
    {
        return new self(
            "The path [{$path}] is already used by another page.", self::TAKEN, $path,
            why: 'A path leads to exactly one page.',
            fix: 'Pick another path, or change the path of the other page first.',
        );
    }
}
