<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaId;

/**
 * Media items moved into a folder, or to the top level (null). Lists only those whose folder changed.
 * Dispatched inside the action's transaction: a sync listener can refuse by throwing, which
 * undoes it.
 */
final readonly class MediaMoved
{
    use Dispatchable;

    /** @param list<MediaId> $mediaIds */
    public function __construct(public array $mediaIds, public ?MediaFolderId $folderId) {}
}
