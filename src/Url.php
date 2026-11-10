<?php

namespace Larasell\Larasell;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Larasell\Larasell\Casts\StringValueCast;
use Larasell\Larasell\Exceptions\InvalidUrl;
use Stringable;

/** A web address with a host: http or https, and https when it's given without a scheme. */
final readonly class Url implements Castable, Stringable
{
    private function __construct(private string $value) {}

    public static function of(string $url): self
    {
        $url = trim($url);

        if ($url !== '' && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_HOST) === null) {
            throw InvalidUrl::of($url);
        }

        return new self($url);
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
