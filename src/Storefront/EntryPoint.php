<?php

namespace Larasell\Larasell\Storefront;

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesChannelId;

/**
 * A host plus an optional path prefix that serves one locale of a sales channel. Currency is not part of
 * the URL: a visit starts in the default currency and may switch to any other the channel sells in.
 */
final readonly class EntryPoint
{
    public function __construct(
        public EntryPointId $id,
        public SalesChannelId $channelId,
        public string $scheme,
        public string $host,
        public ?int $port,
        /** The path prefix without slashes, e.g. "fr", or "" for none. */
        public string $prefix,
        public Locale $locale,
        /** The currency a visit starts in, or null for the channel's default currency. */
        public ?Currency $defaultCurrency,
        /** The entry point StorefrontContext::for() uses for its channel and locale. */
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
