<?php

namespace Larasell\Larasell\Storefront;

use Illuminate\Support\Facades\Context;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesChannels;
use Larasell\Larasell\SalesChannels\SalesContext;
use LogicException;

/**
 * A sales context reached through a storefront entry point: what the storefront and anything that
 * builds absolute URLs, such as links in emails or SEO, needs. Only storefront channels have one, and
 * its entry point always belongs to the context's channel and locale. The currency is not part of the URL:
 * any of the channel's currencies can be used through any of its entry points.
 */
final readonly class StorefrontContext
{
    /** The channel type of storefronts, registered with SalesChannelTypes. */
    public const TYPE = 'storefront';

    /** The hidden Context key that carries the current storefront context into the jobs a request queues. */
    public const KEY = 'larasell.storefront_context';

    public function __construct(
        public SalesContext $sales,
        public EntryPoint $entryPoint,
    ) {
        $type = app(SalesChannels::class)->channel($sales->channelId)['type'] ?? null;

        if ($type !== self::TYPE) {
            throw new LogicException("The sales channel [{$sales->channel}] is not a storefront, so it has no entry points.");
        }

        if (! $entryPoint->channelId->equals($sales->channelId)) {
            throw new LogicException("The entry point [{$entryPoint->url()}] belongs to another sales channel than [{$sales->channel}].");
        }

        if ($entryPoint->locale !== $sales->locale) {
            throw new LogicException("The entry point [{$entryPoint->url()}] serves [{$entryPoint->locale->value}], not [{$sales->locale->value}].");
        }
    }

    /**
     * The storefront context of a sales context, or of a channel (key or ID) in a locale and optionally a
     * currency, through the channel's primary entry point for that locale.
     */
    public static function for(SalesContext|string|SalesChannelId $channel, Locale|string|null $locale = null, ?Currency $currency = null): self
    {
        $sales = $channel instanceof SalesContext ? $channel : SalesContext::for($channel, $locale ?? throw new LogicException('Pass a locale with the channel.'), $currency);

        return app(Storefronts::class)->for($sales);
    }

    /**
     * The same visit in another of the channel's currencies, e.g. the one the visitor picked or their cart
     * is in. The URL stays the same. Throws InvalidSalesChannel for a currency the channel does not sell in.
     */
    public function withCurrency(Currency $currency): self
    {
        return new self(SalesContext::for($this->sales->channelId, $this->sales->locale, $currency), $this->entryPoint);
    }

    /** The storefront context of the current request or of the job it queued, or null outside of both. */
    public static function current(): ?self
    {
        $context = Context::getHidden(self::KEY);

        return $context instanceof self ? $context : null;
    }

    /** Makes this, and its sales context, the current context. */
    public function activate(): void
    {
        Context::addHidden(self::KEY, $this);
        $this->sales->activate();
    }

    /** The absolute URL of a path below the entry point. */
    public function url(string $path = ''): string
    {
        return $this->entryPoint->url($path);
    }
}
