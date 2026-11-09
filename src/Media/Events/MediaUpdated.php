<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Media\MediaId;

/**
 * A media item's details changed. $changed names exactly the fields whose value differs from
 * before, out of "title", "description", "alt" and "visibility". Dispatched inside the action's transaction, and not at all when
 * nothing changed.
 */
final readonly class MediaUpdated
{
    use Dispatchable;

    /** @param  list<string>  $changed */
    public function __construct(public MediaId $mediaId, public array $changed, public Attachments $attachments = new Attachments) {}
}
