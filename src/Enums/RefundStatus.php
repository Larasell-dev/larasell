<?php

namespace Larasell\Larasell\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, match ($this) {
            self::Pending => [self::Succeeded, self::Failed, self::Cancelled],
            self::Succeeded, self::Failed, self::Cancelled => [],
        }, true);
    }
}
