<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaId;

/** Media items to move into a folder, or to the top level (null). */
final readonly class MoveMediaPayload
{
    /** @param list<MediaId> $mediaIds */
    public function __construct(public array $mediaIds, public ?MediaFolderId $folderId) {}
}
