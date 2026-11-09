<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\AssetStatus;
use LogicException;

final class AssetNotReady extends LogicException implements StructuredException
{
    use HasStructuredException;

    public static function forUrl(string $fileName, AssetStatus $status): self
    {
        return self::structured(
            errorCode: 'larasell.media.asset-not-ready',
            message: "The file [{$fileName}] is {$status->value}, so it has no URL yet.",
            why: 'Its provider has not finished with it, or failed to; a URL would lead nowhere.',
            fix: 'Check $asset->status first and show a placeholder until it is ready.',
        );
    }
}
