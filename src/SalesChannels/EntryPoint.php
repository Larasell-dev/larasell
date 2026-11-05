<?php

namespace Larasell\Larasell\SalesChannels;

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/** A host plus an optional path prefix that serves one locale and currency of a sales channel. */
final readonly class EntryPoint
{
    public function __construct(
        public int $id,
        public int $channelId,
        public string $scheme,
        public string $host,
        public ?int $port,
        /** The path prefix without slashes, e.g. "fr", or "" for none. */
        public string $prefix,
        public Locale $locale,
        public Currency $currency,
        /** The entry point SalesContext::for() uses for its channel, locale and currency. */
        public bool $primary,
    ) {}

    /** The absolute URL of a path below this entry point, or of the entry point itself. */
    public function url(string $path = ''): string
    {
        $segments = array_filter([$this->prefix, trim($path, '/')], fn (string $segment): bool => $segment !== '');
        $port = $this->port === null ? '' : ':'.$this->port;
        $path = implode('/', array_map(rawurlencode(...), explode('/', implode('/', $segments))));

        return rtrim($this->scheme.'://'.$this->host.$port.'/'.$path, '/');
    }

    /** Whether a request for the host and decoded path (without leading slash) reaches this entry point. */
    public function serves(string $host, string $path): bool
    {
        return $this->host === strtolower($host)
            && ($this->prefix === '' || $path === $this->prefix || str_starts_with($path, $this->prefix.'/'));
    }
}
