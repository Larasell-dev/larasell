<?php

namespace Larasell\Larasell\Media;

/** Why MoveMedia left a media item where it was. */
enum MediaMoveSkipReason: string
{
    /** It was deleted, e.g. by someone else after it was picked. */
    case Deleted = 'deleted';
}
