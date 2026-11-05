<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\Seo\Contracts\SeoContextResolver;
use Larasell\Larasell\Settings\LocaleSettings;

/**
 * Resolves a request from the entry points in larasell.seo.channels: the host must match, and of
 * the entry points on that host, the one with the longest matching path prefix wins.
 */
final readonly class EntryPointResolver implements SeoContextResolver
{
    public function __construct(private LocaleSettings $locales) {}

    public function resolve(Request $request): ?SeoContext
    {
        $host = strtolower($request->getHost());
        $path = trim($request->path(), '/');
        $match = null;

        foreach ($this->contexts() as $context) {
            $prefixMatches = $context->prefix === ''
                || $path === $context->prefix
                || str_starts_with($path, $context->prefix.'/');

            if ($context->host === $host && $prefixMatches
                && ($match === null || strlen($context->prefix) > strlen($match->prefix))) {
                $match = $context;
            }
        }

        return $match;
    }

    /**
     * Every configured context, one per entry point.
     *
     * @return list<SeoContext>
     */
    public function contexts(): array
    {
        $contexts = [];

        foreach ((array) config('larasell.seo.channels', []) as $channel => $config) {
            foreach ($this->entryPoints((array) $config) as [$url, $locale]) {
                $context = self::context((string) $channel, $url, $locale);
                $key = $context->host.'/'.$context->prefix;

                if (isset($contexts[$key])) {
                    throw new InvalidArgumentException("The SEO entry point [{$context->url()}] is configured more than once.");
                }

                $contexts[$key] = $context;
            }
        }

        return array_values($contexts);
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return list<array{string, mixed}>
     */
    private function entryPoints(array $config): array
    {
        if (isset($config['entry_points'])) {
            return array_map(
                fn (mixed $entryPoint): array => [(string) ($entryPoint['url'] ?? ''), $entryPoint['locale'] ?? null],
                array_values((array) $config['entry_points']),
            );
        }

        $url = rtrim((string) ($config['url'] ?? config('app.url')), '/');
        $locales = isset($config['locales'])
            ? array_map(fn (mixed $locale): Locale => self::locale($locale), array_values((array) $config['locales']))
            : $this->locales->supported();

        if (count($locales) === 1) {
            return [[$url, $locales[0]]];
        }

        return array_map(fn (Locale $locale): array => [$url.'/'.strtolower($locale->value), $locale], $locales);
    }

    private static function context(string $channel, string $url, mixed $locale): SeoContext
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException("The SEO entry point URL [{$url}] of channel [{$channel}] needs a scheme and a host.");
        }

        return new SeoContext(
            channel: $channel,
            locale: self::locale($locale),
            scheme: strtolower($parts['scheme']),
            host: strtolower($parts['host']),
            port: $parts['port'] ?? null,
            prefix: trim($parts['path'] ?? '', '/'),
        );
    }

    private static function locale(mixed $tag): Locale
    {
        $locale = match (true) {
            $tag instanceof Locale => $tag,
            is_string($tag) => Locale::tryFrom(Locale::canonicalize($tag)),
            default => null,
        };

        return $locale ?? throw new InvalidArgumentException('An SEO entry point has an unknown locale.');
    }
}
