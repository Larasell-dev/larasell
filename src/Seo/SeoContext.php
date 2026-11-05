<?php

namespace Larasell\Larasell\Seo;

use Larasell\Larasell\Enums\Locale;

/**
 * A sales channel and locale, reached through one entry point: a host plus an optional path prefix.
 * Every SEO URL belongs to exactly one context.
 */
final readonly class SeoContext
{
    public function __construct(
        public string $channel,
        public Locale $locale,
        public string $scheme,
        public string $host,
        public ?int $port,
        /** The path prefix without slashes, e.g. "fr" or "", for no prefix. */
        public string $prefix,
    ) {}

    /** The absolute URL of a path below this context's entry point, or of the entry point itself. */
    public function url(string $path = ''): string
    {
        $segments = array_filter([$this->prefix, trim($path, '/')], fn (string $segment): bool => $segment !== '');
        $port = $this->port === null ? '' : ':'.$this->port;

        return rtrim($this->scheme.'://'.$this->host.$port.'/'.implode('/', $segments), '/');
    }
}
