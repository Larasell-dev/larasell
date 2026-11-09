<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use LogicException;

final class PrivateMedia extends LogicException implements StructuredException
{
    use HasStructuredException;

    public static function hasNoLastingUrl(string $fileName): self
    {
        return self::structured(
            errorCode: 'larasell.media.private-has-no-url',
            message: "The file [{$fileName}] is private, so it has no lasting URL.",
            why: 'Anyone who gets a lasting URL could open the file forever, whoever they are.',
            fix: 'Check that the requester may see the file, then hand them $asset->temporaryUrl($expiresAt).',
        );
    }
}
