<?php

namespace Larasell\Larasell\SalesChannels;

use Illuminate\Support\Facades\Context;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/**
 * Where something is sold: a sales channel, a locale and a currency, reached through an entry point.
 * It is the only thing passed around for that. It comes from, in this order: the storefront request
 * (set by SetSalesContext), Laravel's Context, which carries it into queued jobs, or the record itself,
 * such as $cart->sales_context. Anything else passes one explicitly.
 */
final readonly class SalesContext
{
    /** The hidden Context key that carries the current context, into queued jobs too. */
    public const KEY = 'larasell.sales_context';

    public function __construct(
        /** The channel's key, e.g. "default" */
        public string $channel,
        public int $channelId,
        public Locale $locale,
        public Currency $currency,
        public EntryPoint $entryPoint,
    ) {}

    /**
     * The context of a channel (key or ID) in a locale, through its primary entry point for the
     * currency, by default the channel's default currency when an entry point uses it.
     */
    public static function for(string|int $channel, Locale|string $locale, ?Currency $currency = null): self
    {
        return app(SalesChannels::class)->for($channel, $locale, $currency);
    }

    /** The context of the current request or job, or null outside of both. */
    public static function current(): ?self
    {
        $context = Context::getHidden(self::KEY);

        return $context instanceof self ? $context : null;
    }

    /** Makes this the current context, e.g. for a console command that works for one channel. */
    public function activate(): void
    {
        Context::addHidden(self::KEY, $this);
    }

    /** The absolute URL of a path below the entry point. */
    public function url(string $path = ''): string
    {
        return $this->entryPoint->url($path);
    }
}
