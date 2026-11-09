<?php

namespace Larasell\Larasell\Media;

/**
 * Where an asset's file is on its way to being usable. A file stored on a disk is ready at once;
 * one a provider like Mux works on is pending, then processing, until the provider says it is
 * ready or failed. Only ready assets hand out URLs.
 */
enum AssetStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    /** Forward only; a failed asset may start over, a ready one stays ready. */
    public function canBecome(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Ready, self::Failed], true),
            self::Processing => in_array($next, [self::Ready, self::Failed], true),
            self::Failed => $next === self::Pending,
            self::Ready => false,
        };
    }
}
