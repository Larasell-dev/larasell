<?php

namespace Larasell\Larasell\SalesChannels;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;

final readonly class SalesChannelId extends Id
{
    protected static function model(): string
    {
        return SalesChannel::class;
    }
}
