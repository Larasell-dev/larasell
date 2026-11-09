<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\AssetStatus;
use Larasell\Larasell\Media\MediaAssetId;
use LogicException;

final class InvalidAssetStatusTransition extends LogicException implements StructuredException
{
    use HasStructuredException;

    public static function between(MediaAssetId $assetId, AssetStatus $from, AssetStatus $to): self
    {
        return self::structured(
            errorCode: 'larasell.media.invalid-asset-status-transition',
            message: "The asset [{$assetId->value}] can't go from {$from->value} to {$to->value}.",
            why: 'An asset only moves forward, from pending through processing to ready or failed; only a failed one may start over.',
            fix: 'Check AssetStatus::canBecome() before moving it, e.g. when a provider event arrives late.',
        );
    }
}
