<?php

namespace Larasell\Larasell\SalesChannels;

use Illuminate\Support\Facades\Context;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/**
 * Where something is sold: a sales channel, a locale and a currency. It knows nothing about how the
 * channel is reached; a storefront wraps it in a Larasell\Larasell\Storefront\StorefrontContext.
 */
final readonly class SalesContext
{
    /** The hidden Context key that carries the current context into the jobs a request queues. */
    public const KEY = 'larasell.sales_context';

    public function __construct(
        /** The channel's key, e.g. "default" */
        public string $channel,
        public int $channelId,
        public Locale $locale,
        public Currency $currency,
    ) {}

    /**
     * The context of a channel (key or ID) in one of its locales and currencies, by default its
     * default currency. Throws InvalidSalesChannel for anything the channel does not sell in.
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
}
