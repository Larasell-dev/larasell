<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\MediaId;
use Larasell\Larasell\Media\MediaMoveSkipReason;

/**
 * What MoveMedia did with each item it was given. Those already in the folder count as moved.
 */
final readonly class MoveMediaResult
{
    /**
     * @param  list<MediaId>  $moved
     * @param  array<int, MediaMoveSkipReason>  $skipped  why each item that stayed did, by media ID
     */
    public function __construct(public array $moved, public array $skipped) {}
}
