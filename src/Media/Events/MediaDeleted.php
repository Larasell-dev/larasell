<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaId;

/**
 * A media item is being deleted. Dispatched inside the action's transaction, right before the
 * row goes: a sync listener can refuse by throwing, e.g. while a product still shows it, which
 * undoes the delete and keeps its files. The files are removed once the delete is committed.
 */
final readonly class MediaDeleted
{
    use Dispatchable;

    public function __construct(public MediaId $mediaId) {}
}
