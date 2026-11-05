<?php

namespace Larasell\Larasell\SalesChannels;

/** Where a channel sells. Only storefronts are built so far; headless or marketplace channels come later. */
enum SalesChannelType: string
{
    case Storefront = 'storefront';
}
